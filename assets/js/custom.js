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
    /*document.querySelectorAll('.sortable').forEach(header => {
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
    });*/

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
    document.addEventListener('click', function(e) {
        const copyLink = e.target.closest('.copy-link');
        if (copyLink) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();

            const href = copyLink.getAttribute('href');
            console.log("Copied Link:" + href);

            if (navigator.clipboard) {
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
        }
    });

    // Video player functionality
    
    
}
