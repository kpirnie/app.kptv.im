// actual dom ready
var DOMReady = function( callback ) {
    if ( document.readyState === "interactive" || document.readyState === "complete" ) {
        callback( );
    } else if ( document.addEventListener ) {
        document.addEventListener( "DOMContentLoaded", callback );
    } else if ( document.attachEvent ) {
        document.attachEvent( "onreadystatechange", function( ) {
            if ( document.readyState != "loading" ) {
                callback( );
            }
        } );
    }
};

// DOM ready event
DOMReady( function( ) {
    console.debug( 'DOM is ready. All libraries are loaded.' );
    MyInit( );
} );

function MyInit( ) {

    // Back to top button
    const inTop = document.querySelector( '.in-totop' );
    if ( inTop ) {
        window.addEventListener( 'scroll', function( ) {
            setTimeout( function( ) {
                window.scrollY > 100 ? 
                    ( inTop.style.opacity = 1, inTop.classList.add( "uk-animation-slide-top" ) ) : 
                    ( inTop.style.opacity -= .1, inTop.classList.remove( "uk-animation-slide-top" ) );
            }, 400 );
        } );
    }

    // Sortable columns
    document.querySelectorAll('.sortable').forEach(header => {
        header.style.cursor = 'pointer';
        header.addEventListener('click', function() {
            const column = this.dataset.column;
            const currentSort = new URLSearchParams(window.location.search).get('sort');
            const currentDir = new URLSearchParams(window.location.search).get('dir');
            
            let newDir = 'asc';
            if (currentSort === column) {
                newDir = currentDir === 'asc' ? 'desc' : 'asc';
            }
            
            const url = new URL(window.location);
            url.searchParams.set('sort', column);
            url.searchParams.set('dir', newDir);
            window.location.href = url.toString();
        });
    });

    // Global active toggle handler - works for all pages
    document.addEventListener('click', function(e) {
        const activeToggle = e.target.closest('.active-toggle');
        if (activeToggle) {
            e.preventDefault();
            e.stopPropagation();
            
            const id = activeToggle.dataset.id;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'form_action';
            actionInput.value = 'toggle-active';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = id;
            
            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }
    });

    // Stream channel click-to-edit functionality
    document.addEventListener('click', function(e) {
        // Check for stream channel cell click
        const channelCell = e.target.closest('.stream-channel.channel-cell');
        if (channelCell && !channelCell.querySelector('input')) {
            e.stopPropagation();

            const cell = channelCell;
            const currentValue = cell.textContent.trim();
            const row = cell.closest('tr');
            const streamId = row.querySelector('.record-checkbox').value;
            
            // Create input field
            const input = document.createElement('input');
            input.type = 'text';
            input.value = currentValue;
            input.className = 'uk-input uk-form-small';
            
            // Clear cell and add input
            cell.textContent = '';
            cell.appendChild(input);
            input.focus();
            
            // Handle Enter key to save
            const handleKeyDown = function(e) {
                if (e.key === 'Enter') {
                    saveChannelChange(streamId, input.value.trim(), cell, currentValue);
                    input.removeEventListener('keydown', handleKeyDown);
                    input.removeEventListener('blur', handleBlur);
                } else if (e.key === 'Escape') {
                    revertChannelCell(cell, currentValue);
                    input.removeEventListener('keydown', handleKeyDown);
                    input.removeEventListener('blur', handleBlur);
                }
            };
            
            // Handle blur (click outside) to save
            const handleBlur = function() {
                saveChannelChange(streamId, input.value.trim(), cell, currentValue);
                input.removeEventListener('keydown', handleKeyDown);
                input.removeEventListener('blur', handleBlur);
            };
            
            input.addEventListener('keydown', handleKeyDown);
            input.addEventListener('blur', handleBlur);
        }
    });

    // Stream name click-to-edit functionality
    document.addEventListener('click', function(e) {
        // Check for stream name cell click
        const nameCell = e.target.closest('.stream-name.name-cell');
        if (nameCell && !nameCell.querySelector('input')) {
            e.stopPropagation();

            const cell = nameCell;
            const currentValue = cell.textContent.trim();
            const row = cell.closest('tr');
            const streamId = row.querySelector('.record-checkbox').value;
            
            // Create input field
            const input = document.createElement('input');
            input.type = 'text';
            input.value = currentValue;
            input.className = 'uk-input uk-form-small';
            
            // Clear cell and add input
            cell.textContent = '';
            cell.appendChild(input);
            input.focus();
            
            // Handle Enter key to save
            const handleKeyDown = function(e) {
                if (e.key === 'Enter') {
                    saveNameChange(streamId, input.value.trim(), cell, currentValue);
                    input.removeEventListener('keydown', handleKeyDown);
                    input.removeEventListener('blur', handleBlur);
                } else if (e.key === 'Escape') {
                    revertCell(cell, currentValue);
                    input.removeEventListener('keydown', handleKeyDown);
                    input.removeEventListener('blur', handleBlur);
                }
            };
            
            // Handle blur (click outside) to save
            const handleBlur = function() {
                saveNameChange(streamId, input.value.trim(), cell, currentValue);
                input.removeEventListener('keydown', handleKeyDown);
                input.removeEventListener('blur', handleBlur);
            };
            
            input.addEventListener('keydown', handleKeyDown);
            input.addEventListener('blur', handleBlur);
        }
    });

    // Add this with your other event listeners in the MyInit function
    document.querySelectorAll('.activate-streams').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const checkedBoxes = document.querySelectorAll('.record-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('Please select at least one stream to activate.');
                return;
            }

            if (!confirm(`Activate ${checkedBoxes.length} selected stream(s)?`)) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'form_action';
            actionInput.value = 'activate-streams';
            form.appendChild(actionInput);

            const urlParams = new URLSearchParams(window.location.search);
            const sortInput = document.createElement('input');
            sortInput.type = 'hidden';
            sortInput.name = 'sort';
            sortInput.value = urlParams.get('sort') || 'sp_priority';
            form.appendChild(sortInput);

            const dirInput = document.createElement('input');
            dirInput.type = 'hidden';
            dirInput.name = 'dir';
            dirInput.value = urlParams.get('dir') || 'asc';
            form.appendChild(dirInput);

            checkedBoxes.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = checkbox.value;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        });
    });

    // Row selection functionality
    const tableRows = document.querySelectorAll('tbody tr');
    tableRows.forEach(row => {
        const cells = Array.from(row.cells).slice(0, -1);
        const checkbox = row.querySelector('.record-checkbox');
        
        cells.forEach(cell => {
            // Skip cells that have their own click handlers
            if (cell.querySelector('.active-toggle') || 
                cell.classList.contains('stream-name') || 
                cell.classList.contains('stream-channel') ||
                cell.querySelector('.copy-link')) {
                return; // Don't add checkbox toggle to these cells
            }
            
            cell.style.cursor = 'pointer';
            cell.addEventListener('click', (e) => {
                if (e.target.tagName === 'A' || 
                    e.target.tagName === 'BUTTON' || 
                    e.target.tagName === 'INPUT' ||
                    e.target.classList.contains('copy-link')) return;
                if (e.target === checkbox) return;
                
                checkbox.checked = !checkbox.checked;
                const event = new Event('change');
                checkbox.dispatchEvent(event);
            });
        });
    });

    // Checkbox management - updated to handle multiple select-all checkboxes
    const selectAllCheckboxes = document.querySelectorAll('.select-all');
    const checkboxes = document.querySelectorAll('.record-checkbox');
    const deleteSelectedBtn = document.getElementById('delete-selected');

    // Function to update all select-all checkboxes
    function updateSelectAllCheckboxes() {
        const allChecked = checkboxes.length > 0 && [...checkboxes].every(cb => cb.checked);
        selectAllCheckboxes.forEach(checkbox => {
            checkbox.checked = allChecked;
            checkbox.indeterminate = !allChecked && [...checkboxes].some(cb => cb.checked);
        });
    }

    // Add change event to all select-all checkboxes
    selectAllCheckboxes.forEach(selectAll => {
        selectAll.addEventListener('change', function() {
            const isChecked = this.checked;
            checkboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            updateDeleteButtonState();
        });
    });

    // Add change event to all record checkboxes
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectAllCheckboxes();
            updateDeleteButtonState();
        });
    });

    function updateDeleteButtonState() {
        if (!deleteSelectedBtn) return;
        const checkedBoxes = document.querySelectorAll('.record-checkbox:checked');
        deleteSelectedBtn.disabled = checkedBoxes.length === 0;
    }

    // Initialize the state
    updateSelectAllCheckboxes();
    updateDeleteButtonState();

    // Delete selected items
    document.querySelectorAll('.delete-selected').forEach(button => {
        button.addEventListener('click', function() {
            const checkedBoxes = document.querySelectorAll('.record-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('Please select at least one item to delete.');
                return;
            }

            if (!confirm(`Delete ${checkedBoxes.length} selected item(s)?`)) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'form_action';
            actionInput.value = 'delete-multiple';
            form.appendChild(actionInput);

            const urlParams = new URLSearchParams(window.location.search);
            const sortInput = document.createElement('input');
            sortInput.type = 'hidden';
            sortInput.name = 'sort';
            sortInput.value = urlParams.get('sort') || 'sp_priority';
            form.appendChild(sortInput);

            const dirInput = document.createElement('input');
            dirInput.type = 'hidden';
            dirInput.name = 'dir';
            dirInput.value = urlParams.get('dir') || 'asc';
            form.appendChild(dirInput);

            checkedBoxes.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = checkbox.value;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        });
    });

    // move streams handler
    document.querySelectorAll('.move-to-live, .move-to-series, .move-to-other').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Determine if this is a single item move or bulk move
            const isSingleMove = this.classList.contains('single-move');
            let checkedBoxes;
            let streamId;
            
            if (isSingleMove) {
                // For single item move, get the checkbox from the same row
                const row = this.closest('tr');
                streamId = row.querySelector('.record-checkbox').value;
                if (!streamId) return;
            } else {
                // For bulk move, get all checked checkboxes
                checkedBoxes = document.querySelectorAll('.record-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    alert('Please select at least one item to move.');
                    return;
                }
            }

            const destination = this.classList.contains('move-to-live') ? 'live' : 
                            this.classList.contains('move-to-series') ? 'series' : 'other';
            
            const itemCount = isSingleMove ? 1 : checkedBoxes.length;
            if (!confirm(`Move ${itemCount} item(s) to ${destination}?`)) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'form_action';
            actionInput.value = 'move-to-' + destination;
            form.appendChild(actionInput);

            const urlParams = new URLSearchParams(window.location.search);
            const sortInput = document.createElement('input');
            sortInput.type = 'hidden';
            sortInput.name = 'sort';
            sortInput.value = urlParams.get('sort') || 'sp_priority';
            form.appendChild(sortInput);

            const dirInput = document.createElement('input');
            dirInput.type = 'hidden';
            dirInput.name = 'dir';
            dirInput.value = urlParams.get('dir') || 'asc';
            form.appendChild(dirInput);

            if (isSingleMove) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = streamId;
                form.appendChild(input);
            } else {
                checkedBoxes.forEach(checkbox => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = checkbox.value;
                    form.appendChild(input);
                });
            }

            document.body.appendChild(form);
            form.submit();
        });
    });

    function revertCell(cell, originalValue) {
        cell.textContent = originalValue;
        cell.classList.add('stream-name');
        cell.classList.add('name-cell');
    }

    function revertChannelCell(cell, originalValue) {
        cell.textContent = originalValue;
        cell.classList.add('stream-channel');
        cell.classList.add('channel-cell');
    }
    
    function saveNameChange(streamId, newName, cell, originalValue) {
        if (!newName || newName === originalValue) {
            revertCell(cell, originalValue);
            return;
        }
        
        // Show loading state
        const spinner = document.createElement('span');
        spinner.setAttribute('uk-spinner', 'ratio: 0.5');
        cell.textContent = '';
        cell.appendChild(spinner);
        
        // Prepare form data
        const formData = new FormData();
        formData.append('form_action', 'update-name');
        formData.append('id', streamId);
        formData.append('s_name', newName);
        
        // Send AJAX request
        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();  // ← Fixed: Return the promise directly
        })
        .then(text => {  // ← Fixed: Handle text in separate .then()
            return text ? JSON.parse(text) : {};
        })
        .then(data => {
            if (data.success) {
                cell.textContent = newName;
                cell.classList.add('stream-name');
                cell.classList.add('name-cell');
                
                // Show success notification
                if (typeof UIkit !== 'undefined' && UIkit.notification) {
                    UIkit.notification({
                        message: 'Name updated successfully',
                        status: 'success',
                        pos: 'top-right',
                        timeout: 2000
                    });
                }
            } else {
                throw new Error(data.message || 'Update failed');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            revertCell(cell, originalValue);
            
            // Show error notification
            if (typeof UIkit !== 'undefined' && UIkit.notification) {
                UIkit.notification({
                    message: 'Error saving: ' + error.message,
                    status: 'danger',
                    pos: 'top-right',
                    timeout: 5000
                });
            }
        });
    }

    function saveChannelChange(streamId, newChannel, cell, originalValue) {
        if (!newChannel || newChannel === originalValue) {
            revertChannelCell(cell, originalValue);
            return;
        }
        
        // Show loading state
        const spinner = document.createElement('span');
        spinner.setAttribute('uk-spinner', 'ratio: 0.5');
        cell.textContent = '';
        cell.appendChild(spinner);
        
        // Prepare form data
        const formData = new FormData();
        formData.append('form_action', 'update-channel');
        formData.append('id', streamId);
        formData.append('s_channel', newChannel);
        
        // Send AJAX request
        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();  // ← Fixed
        })
        .then(text => {  // ← Fixed
            return text ? JSON.parse(text) : {};
        })
        .then(data => {
            if (data.success) {
                cell.textContent = newChannel;
                cell.classList.add('stream-channel');
                cell.classList.add('channel-cell');
                
                // Show success notification
                if (typeof UIkit !== 'undefined' && UIkit.notification) {
                    UIkit.notification({
                        message: 'Channel updated successfully',
                        status: 'success',
                        pos: 'top-right',
                        timeout: 2000
                    });
                }
            } else {
                throw new Error(data.message || 'Update failed');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            revertChannelCell(cell, originalValue);
            
            // Show error notification
            if (typeof UIkit !== 'undefined' && UIkit.notification) {
                UIkit.notification({
                    message: 'Error saving channel: ' + error.message,
                    status: 'danger',
                    pos: 'top-right',
                    timeout: 5000
                });
            }
        });
    }
    

    // Add click event listeners to all elements with class 'copy-link'
    document.querySelectorAll('.copy-link').forEach(link => {
        link.addEventListener('click', function(e) {
            // Prevent the default click action AND stop propagation
            e.preventDefault();
            e.stopPropagation(); // This prevents the checkbox from being toggled

            // Get the href attribute of the clicked link
            const href = this.getAttribute('href');

            if (navigator.clipboard) {
                // Use the Clipboard API to copy the href
                navigator.clipboard.writeText(href).then(function() {
                    UIkit.notification({
                        message: 'Your URL has been copied to your clipboard!',
                        status: 'success',
                        pos: 'top-center',
                        timeout: 5000
                    });
                }).catch(function(err) {
                    UIkit.notification({
                        message: 'Failed to copy: ' + err,
                        status: 'danger',
                        pos: 'top-center',
                        timeout: 5000
                    });
                });
            } else {
                // Fallback for browsers that don't support the Clipboard API
                const tempInput = document.createElement('input');
                document.body.appendChild(tempInput);
                tempInput.value = href;
                tempInput.select();
                
                try {
                    const successful = document.execCommand('copy');
                    if (successful) {
                        UIkit.notification({
                            message: 'Your URL has been copied to your clipboard!',
                            status: 'success',
                            pos: 'top-center',
                            timeout: 5000
                        });
                    } else {
                        throw new Error('Copy command failed');
                    }
                } catch (err) {
                    UIkit.notification({
                        message: 'Failed to copy: ' + err,
                        status: 'danger',
                        pos: 'top-center',
                        timeout: 5000
                    });
                }
                document.body.removeChild(tempInput);
            }
        });
    });

    // Video player functionality
// Basic video player with loading indicator
    let player = null;
    let hls = null;
    let isModalOpen = false;
    let currentAttempt = null; // Track current loading attempt

    // Initialize video player when modal opens
    if (typeof UIkit !== 'undefined') {
        UIkit.util.on('#vid_modal', 'show', function () {
            console.log('Modal opening...');
            isModalOpen = true;
            
            if (!player && typeof videojs !== 'undefined') {
                player = videojs('the_streamer', {
                    fluid: true,
                    responsive: true,
                    controls: true,
                    preload: 'auto'
                });

                player.ready(() => {
                    console.log('Video.js player is ready');
                });
            }
        });

        // Cleanup when modal closes
        UIkit.util.on('#vid_modal', 'hide', function () {
            console.log('Modal closing - cleaning up...');
            isModalOpen = false;
            
            // Hide loading indicator
            hideLoadingIndicator();
            
            // Cancel any ongoing attempt
            if (currentAttempt) {
                clearTimeout(currentAttempt.timeout1);
                clearTimeout(currentAttempt.timeout2);
                currentAttempt = null;
            }
            
            // Cleanup HLS.js
            if (hls) {
                console.log('Destroying HLS.js instance');
                hls.destroy();
                hls = null;
            }
            
            // Cleanup Video.js
            if (player) {
                player.controls(true); // Ensure controls are re-enabled
                player.pause();
                player.src('');
                player.load();
            }
        });
    }

    function showLoadingIndicator(message = 'Loading stream...') {
        // Remove any existing loading indicator
        hideLoadingIndicator();
        
        // Create loading overlay
        const video = player.el().querySelector('video');
        if (!video) return;
        
        const overlay = document.createElement('div');
        overlay.id = 'stream-loading-overlay';
        overlay.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            color: white;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        `;
        
        overlay.innerHTML = `
            <div uk-spinner="ratio: 2" style="margin-bottom: 16px;"></div>
            <div style="font-size: 16px; font-weight: 500;">${message}</div>
        `;
        
        // Add to video container
        const videoContainer = video.parentElement;
        videoContainer.style.position = 'relative';
        videoContainer.appendChild(overlay);
        
        console.log('Loading indicator shown:', message);
    }

    function hideLoadingIndicator() {
        const overlay = document.getElementById('stream-loading-overlay');
        if (overlay) {
            overlay.remove();
            console.log('Loading indicator hidden');
        }
    }

    // Handle play-stream button clicks
    document.addEventListener('click', function(e) {
        const playButton = e.target.closest('.play-stream');
        if (playButton) {
            console.log('Play button clicked!');
            e.preventDefault();
            e.stopPropagation();
            
            const streamUrl = playButton.getAttribute('data-stream-url');
            console.log('Original Stream URL:', streamUrl);
            
            if (streamUrl) {
                // Show the modal
                UIkit.modal('#vid_modal').show();
                
                // Wait for modal to be visible, then load video
                setTimeout(() => {
                    if (player && isModalOpen) {
                        console.log('Loading video...');
                        loadStream(streamUrl);
                    }
                }, 500);
            }
        }
    });

    function loadStream(streamUrl) {
        if (!player || !isModalOpen) return;
        
        console.log('=== Loading stream:', streamUrl);
        
        // Show initial loading indicator
        showLoadingIndicator('Preparing stream...');
        
        // Cancel any ongoing attempt
        if (currentAttempt) {
            clearTimeout(currentAttempt.timeout1);
            clearTimeout(currentAttempt.timeout2);
            currentAttempt = null;
        }
        
        // Clean up any existing HLS instance
        if (hls) {
            hls.destroy();
            hls = null;
        }
        
        // Check if this is a .ts file and test if .m3u8 version exists
        if (streamUrl.toLowerCase().includes('.ts')) {
            showLoadingIndicator('Checking stream compatibility...');
            const m3u8Url = streamUrl.replace(/\.ts$/i, '.m3u8');
            console.log('Testing if .m3u8 version exists:', m3u8Url);
            
            // Test if .m3u8 version is accessible
            fetch('/proxy/stream?url=' + encodeURIComponent(m3u8Url), { 
                method: 'HEAD',
                signal: AbortSignal.timeout(5000) // 5 second timeout
            })
            .then(response => {
                if (response.ok) {
                    console.log('✓ Found .m3u8 version, using it');
                    showLoadingIndicator('Loading stream...');
                    startStreamLoad(m3u8Url);
                } else {
                    console.log('✗ .m3u8 version not found, .ts files cannot be played in browsers');
                    hideLoadingIndicator();
                    showError(streamUrl, true);
                }
            })
            .catch(err => {
                console.log('✗ Error testing .m3u8 version:', err.message);
                hideLoadingIndicator();
                showError(streamUrl, true);
            });
            return;
        }
        
        // For non-.ts files, proceed normally
        showLoadingIndicator('Loading stream...');
        startStreamLoad(streamUrl);
    }
    
    function startStreamLoad(actualUrl) {
        const isHLS = actualUrl.toLowerCase().includes('.m3u8');
        
        if (isHLS && window.Hls && Hls.isSupported()) {
            console.log('=== Using HLS.js');
            // Don't reset Video.js player when using HLS.js
            player.pause();
            tryHLS(actualUrl);
        } else {
            console.log('=== Using Video.js');
            // Reset player for Video.js
            player.pause();
            player.src('');
            tryVideoJS(actualUrl);
        }
    }
    
    function tryHLS(streamUrl) {
        // Get the raw video element, not the Video.js player
        const video = player.el().querySelector('video');
        if (!video) {
            console.error('Video element not found!');
            hideLoadingIndicator();
            return;
        }
        
        console.log('Creating HLS.js instance...');
        showLoadingIndicator('Initializing HLS player...');
        
        // Disable Video.js controls temporarily for HLS.js
        player.controls(false);
        
        hls = new Hls({
            debug: false,
            enableWorker: true,
            fragLoadingTimeOut: 20000,
            manifestLoadingTimeOut: 10000
        });
        
        let hasStartedPlaying = false;
        
        hls.on(Hls.Events.MEDIA_ATTACHED, function() {
            console.log('HLS.js: Media attached');
            showLoadingIndicator('Loading stream data...');
        });
        
        hls.on(Hls.Events.MANIFEST_PARSED, function(event, data) {
            console.log('✓ HLS.js: Manifest parsed, levels:', data.levels.length);
            if (isModalOpen && !hasStartedPlaying) {
                hasStartedPlaying = true;
                console.log('Starting HLS playback...');
                showLoadingIndicator('Starting playback...');
                // Re-enable controls
                player.controls(true);
                // Cancel pending timeouts since we're successful
                if (currentAttempt) {
                    clearTimeout(currentAttempt.timeout1);
                    clearTimeout(currentAttempt.timeout2);
                    currentAttempt = null;
                }
                video.play().then(() => {
                    console.log('✓ HLS.js: Playing successfully');
                    hideLoadingIndicator(); // Hide loading when playback starts
                }).catch(err => {
                    console.error('✗ HLS.js play error:', err);
                    hideLoadingIndicator();
                });
            }
        });
        
        hls.on(Hls.Events.ERROR, function(event, data) {
            console.error('✗ HLS.js error:', data.type, data.details);
            
            if (data.fatal && !hasStartedPlaying) {
                console.log('Fatal HLS error, trying Video.js...');
                showLoadingIndicator('HLS failed, trying alternative method...');
                player.controls(true); // Re-enable controls
                // Cancel timeouts and clean up
                if (currentAttempt) {
                    clearTimeout(currentAttempt.timeout1);
                    clearTimeout(currentAttempt.timeout2);
                    currentAttempt = null;
                }
                if (hls) {
                    hls.destroy();
                    hls = null;
                }
                tryVideoJS(streamUrl);
            }
        });
        
        // Try proxy first, then direct
        const proxyUrl = '/proxy/stream?url=' + encodeURIComponent(streamUrl);
        console.log('HLS.js trying proxy:', proxyUrl);
        
        hls.attachMedia(video);
        hls.loadSource(proxyUrl);
        
        // Set up timeout tracking
        currentAttempt = {
            timeout1: setTimeout(() => {
                if (isModalOpen && hls && !hasStartedPlaying) {
                    console.log('HLS proxy timeout, trying direct...');
                    showLoadingIndicator('Trying direct connection...');
                    hls.loadSource(streamUrl);
                    
                    // Final fallback to Video.js after another 8 seconds
                    currentAttempt.timeout2 = setTimeout(() => {
                        if (isModalOpen && !hasStartedPlaying) {
                            console.log('HLS direct timeout, trying Video.js...');
                            showLoadingIndicator('Trying alternative player...');
                            player.controls(true); // Re-enable controls
                            if (hls) {
                                hls.destroy();
                                hls = null;
                            }
                            currentAttempt = null;
                            tryVideoJS(streamUrl);
                        }
                    }, 8000);
                }
            }, 8000),
            timeout2: null
        };
    }
    
    function tryVideoJS(streamUrl) {
        console.log('Trying Video.js with:', streamUrl);
        
        // Clear any remaining timeouts
        if (currentAttempt) {
            clearTimeout(currentAttempt.timeout1);
            clearTimeout(currentAttempt.timeout2);
            currentAttempt = null;
        }
        
        const isHLS = streamUrl.toLowerCase().includes('.m3u8');
        const sourceType = isHLS ? 'application/x-mpegURL' : 'video/mp4';
        
        // Try proxy first
        const proxyUrl = '/proxy/stream?url=' + encodeURIComponent(streamUrl);
        console.log('Video.js trying proxy:', proxyUrl);
        
        player.src({
            src: proxyUrl,
            type: sourceType
        });
        
        player.ready(() => {
            if (isModalOpen) {
                console.log('Video.js ready, starting playback...');
                player.play().then(() => {
                    console.log('✓ Video.js: Playing successfully');
                }).catch(err => {
                    console.error('✗ Video.js proxy failed:', err);
                    // Try direct - but only once
                    if (isModalOpen) {
                        console.log('Trying Video.js direct...');
                        player.src({
                            src: streamUrl,
                            type: sourceType
                        });
                        player.play().catch(directErr => {
                            console.error('✗ Video.js direct failed:', directErr);
                            showError(streamUrl);
                        });
                    }
                });
            }
        });
    }
    
    function showError(streamUrl, isTsFile = false) {
        if (!isModalOpen) return;
        
        let message;
        if (isTsFile) {
            message = 'Transport Stream (.ts) files cannot be played in web browsers. Copy this url: ' + streamUrl + ' and past it into something like VLC to test it.';
        } else if (streamUrl.includes('.ts')) {
            message = 'Transport stream files (.ts) typically need to be part of an HLS playlist (.m3u8) to work in browsers.';
        } else {
            message = 'Unable to play this stream. The stream may be offline, require authentication, or use an unsupported format.';
        }
        
        UIkit.notification({
            message: message,
            status: 'danger',
            pos: 'top-right',
            timeout: 15000,
            close: true // Explicitly enable close button only
        });

        // Then immediately after, disable message clicks
        setTimeout(() => {
            const notifications = document.querySelectorAll('.uk-notification-message');
            notifications.forEach(notification => {
                // Remove UIKit's click handlers by cloning the element
                const newNotification = notification.cloneNode(true);
                notification.parentNode.replaceChild(newNotification, notification);
                
                // Add our own handler that only allows close button clicks
                newNotification.addEventListener('click', function(e) {
                    if (!e.target.closest('.uk-notification-close')) {
                        e.preventDefault();
                        e.stopPropagation();
                        return false;
                    }
                }, true); // Use capture phase
            });
        }, 200);
        
    }
    
}