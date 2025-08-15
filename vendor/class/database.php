<?php
/**
 * This is our database class
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 * 
 */

// throw it under my namespace
namespace KPT;

// no direct access
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// if the class is not already in userspace
if( ! class_exists( 'Database' ) ) {

    /** 
     * Class Database
     * 
     * Database Class
     * 
     * @since 8.4
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     * 
     * @property protected $db_handle: The database handle used throughout the class
     * @property protected $current_query: The current query being built
     * @property protected $query_params: Parameters for the current query
     * @property protected $fetch_mode: The fetch mode for the current query
     * 
     */
    class Database {

        // hold the database handle object
        protected ?\PDO $db_handle = null;

        // query builder properties
        protected string $current_query = '';
        protected array $query_params = [];
        protected int $fetch_mode = \PDO::FETCH_OBJ;
        protected bool $fetch_single = false;

        /**
         * __construct
         * 
         * Initialize the database connection
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return void
         */
        public function __construct( ) {

            // try to establish database connection
            try {
       
                // get our database settings
                $db_settings = KPT::get_setting( 'database' );

                // build the dsn string
                $dsn = "mysql:host={$db_settings -> server};dbname={$db_settings -> schema}";
                
                // setup the PDO connection
                $this -> db_handle = new \PDO( $dsn, $db_settings -> username, $db_settings -> password );

                // Set character encoding
                $this -> db_handle -> exec( "SET NAMES {$db_settings -> charset} COLLATE {$db_settings -> collation}" );
                $this -> db_handle -> exec( "SET CHARACTER SET {$db_settings -> charset}" );
                $this -> db_handle -> exec( "SET collation_connection = {$db_settings -> collation}" );

                // debug logging
                LOG::debug( "Database Character Encoding Set", [
                    'charset' => $db_settings -> charset,
                    'collation' => $db_settings -> collation
                ] );

                // set pdo attributes
                $this -> db_handle -> setAttribute( \PDO::ATTR_EMULATE_PREPARES, false );
                $this -> db_handle -> setAttribute( \PDO::ATTR_PERSISTENT, true );
                $this -> db_handle -> setAttribute( \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true );
                $this -> db_handle -> setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );

                // debug logging
                LOG::debug( "Database Constructor Completed Successfully" );

            // whoopsie...
            } catch ( \Exception $e ) {

                // error logging
                LOG::error( "Database Constructor Failed", [
                    'message' => $e -> getMessage( ),
                ] );

                throw $e;
            }
        }

        /**
         * __destruct
         * 
         * Clean up the database connection
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return void
         */
        public function __destruct( ) {

            // try to clean up
            try {

                // reset
                $this -> reset( );

                // close the connection
                $this -> db_handle = null;

                // clear em our
                unset( $this -> db_handle );

                // debug logging
                LOG::debug( "Database Destructor Completed Successfully" );

            // whoopsie...
            } catch ( \Exception $e ) {

                // error logging
                LOG::error( "Database Destructor Error", [
                    'message' => $e -> getMessage( ),
                ] );
            }
        }

        /**
         * query
         * 
         * Set the query to be executed
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $query The SQL query to prepare
         * @return self Returns self for method chaining
         */
        public function query( string $query ) : self {
            
            // reset the query builder state
            $this -> reset( );
            
            // store the query
            $this -> current_query = $query;

            // debug logging
            LOG::debug( "Database Query Stored Successfully", [] );
            
            // return self for chaining
            return $this;
        }

        /**
         * bind
         * 
         * Bind parameters for the current query
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array|mixed $params Parameters to bind (array or single value)
         * @return self Returns self for method chaining
         */
        public function bind( mixed $params ) : self {
            
            // if single value passed, wrap in array
            if ( ! is_array( $params ) ) {
                $params = [ $params ];
            }
            
            // store the parameters
            $this -> query_params = $params;

            // debug logging
            LOG::debug( "Database Parameters Bound Successfully", [
                'param_count' => count( $this -> query_params ),
                'param_types' => array_map( 'gettype', $this -> query_params )
            ] );
            
            // return self for chaining
            return $this;
        }

        /**
         * single
         * 
         * Set fetch mode to return single record
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return self Returns self for method chaining
         */
        public function single( ) : self {
            
            // set fetch single flag
            $this -> fetch_single = true;
            
            // return self for chaining
            return $this;
        }

        /**
         * many
         * 
         * Set fetch mode to return multiple records (default)
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return self Returns self for method chaining
         */
        public function many( ) : self {
            
            // set fetch single flag
            $this -> fetch_single = false;
            
            // return self for chaining
            return $this;
        }

        /**
         * as_array
         * 
         * Set fetch mode to return arrays instead of objects
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return self Returns self for method chaining
         */
        public function as_array( ) : self {
            
            // set fetch mode to array
            $this -> fetch_mode = \PDO::FETCH_ASSOC;
            
            // return self for chaining
            return $this;
        }

        /**
         * as_object
         * 
         * Set fetch mode to return objects (default)
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return self Returns self for method chaining
         */
        public function as_object( ) : self {
            
            // set fetch mode to object
            $this -> fetch_mode = \PDO::FETCH_OBJ;
            
            // return self for chaining
            return $this;
        }

        /**
         * fetch
         * 
         * Execute SELECT query and fetch results
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param ?int $limit Optional limit for number of records
         * @return mixed Returns query results (object/array/bool)
         */
        public function fetch( ?int $limit = null ) : mixed {
            
            // validate we have a query
            if ( empty( $this -> current_query ) ) {

                // error logging
                LOG::error( "Database Fetch Failed - No Query Set" );

                throw new \RuntimeException( 'No query has been set. Call query() first.' );
            }
            
            // if limit is provided, determine fetch mode
            if ( $limit === 1 ) {

                // set the single property
                $this -> fetch_single = true;

                // debug logging
                LOG::debug( "Database Fetch Mode Auto-Set to Single (limit=1)" );
            
            // otherwise
            } elseif ( $limit > 1 ) {

                // set it false
                $this -> fetch_single = false;

                // debug logging
                LOG::debug( "Database Fetch Mode Auto-Set to Many", [
                    'limit' => $limit
                ] );
            }

            // try to execute the query
            try {
            
                // prepare the statement
                $stmt = $this -> db_handle -> prepare( $this -> current_query );
                
                // bind parameters if we have any
                $this -> bind_params( $stmt, $this -> query_params );
                
                // execute the query
                if ( ! $stmt -> execute( ) ) {

                    // error logging
                    LOG::error( "Database Query Execution Failed" );

                    return false;
                }
                
                // fetch based on mode
                if ( $this -> fetch_single ) {

                    // fetch only one record
                    $result = $stmt -> fetch( $this -> fetch_mode );

                    // close the cursor
                    $stmt -> closeCursor( );

                    // debug logging
                    LOG::debug( "Database Single Record Fetched", [
                        'has_result' => ! empty( $result ),
                        'result_type' => gettype( $result )
                    ] );

                    // return the result
                    return ! empty( $result ) ? $result : false;
                
                } else {
                
                    // fetch all records
                    $results = $stmt -> fetchAll( $this -> fetch_mode );

                    // close the cursor
                    $stmt -> closeCursor( );

                    // debug logging
                    LOG::debug( "Database Multiple Records Fetched", [
                        'has_results' => ! empty( $results ),
                        'result_count' => is_array( $results ) ? count( $results ) : 0
                    ] );

                    // return the resultset
                    return ! empty( $results ) ? $results : false;
                }

            // whoopsie...
            } catch ( \Exception $e ) {

                // error logging
                LOG::error( "Database Fetch Error", [
                    'message' => $e -> getMessage( ),
                ] );

                throw $e;
            }
        }

        /**
         * execute
         * 
         * Execute non-SELECT queries (INSERT, UPDATE, DELETE)
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return mixed Returns last insert ID for INSERT, affected rows for UPDATE/DELETE, or false on failure
         */
        public function execute( ) : mixed {
            
            // validate we have a query
            if ( empty( $this -> current_query ) ) {

                // error logging
                LOG::error( "Database Execute Failed - No Query Set" );

                // throw an exception
                throw new \RuntimeException( 'No query has been set. Call query() first.' );
            }

            // try to execute the query
            try {
            
                // prepare the statement
                $stmt = $this -> db_handle -> prepare( $this -> current_query );

                // debug logging
                LOG::debug( "Database Statement Prepared for Execute" );
                
                // bind parameters if we have any
                $this -> bind_params( $stmt, $this -> query_params );
                
                // execute the query
                $success = $stmt -> execute( );
                
                // it was not successful, log an error and return false
                if ( ! $success ) {

                    // error logging
                    LOG::error( "Database Execute Failed", [] );
                    return false;
                }

                // debug logging
                LOG::debug( "Database Query Executed Successfully" );
                
                // determine return value based on query type
                $query_type = strtoupper( substr( trim( $this -> current_query ), 0, 6 ) );
                
                // figure out what kind of query are we running for the return value
                switch ( $query_type ) {
                    case 'INSERT':

                        // return last insert ID for inserts
                        $id = $this -> db_handle -> lastInsertId( );
                        $result = $id ?: true;

                        // debug logging
                        LOG::debug( "Database INSERT Executed", [] );

                        return $result;
                        
                    case 'UPDATE':
                    case 'DELETE':

                        // return affected rows for updates/deletes
                        $affected_rows = $stmt -> rowCount( );

                        // debug logging
                        LOG::debug( "Database {$query_type} Executed", [] );

                        return $affected_rows;
                        
                    default:

                        // debug logging
                        LOG::debug( "Database {$query_type} Executed", [] );

                        // return success for other queries
                        return $success;
                }

            // whoopsie...
            } catch ( \Exception $e ) {

                // error logging
                LOG::error( "Database Execute Error", [
                    'message' => $e -> getMessage( ),
                ] );

                throw $e;
            }
        }

        /**
         * get_last_id
         * 
         * Get the last inserted ID
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string|false Returns the last insert ID or false
         */
        public function get_last_id( ) : string|false {

            // try to get the last insert ID
            try {
            
                // return the last id
                return $this -> db_handle -> lastInsertId( ) ?? 0;

            // whoopsie...
            } catch ( \Exception $e ) {

                // error logging
                LOG::error( "Database Get Last ID Error", [
                    'message' => $e -> getMessage( )
                ] );

                return false;
            }
        }

        /**
         * transaction
         * 
         * Begin a database transaction
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if transaction started successfully
         */
        public function transaction( ) : bool {

            // try to begin transaction
            try {

                // begin the transaction
                return $this -> db_handle -> beginTransaction( );

            // whoopsie...
            } catch ( \Exception $e ) {

                // error logging
                LOG::error( "Database Transaction Start Error", [
                    'message' => $e -> getMessage( )
                ] );

                return false;
            }
        }

        /**
         * commit
         * 
         * Commit the current transaction
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if transaction committed successfully
         */
        public function commit( ) : bool {

            // try to commit transaction
            try {

                // commit the transaction
                return $this -> db_handle -> commit( );

            // whoopsie...
            } catch ( \Exception $e ) {

                // error logging
                LOG::error( "Database Transaction Commit Error", [
                    'message' => $e -> getMessage( )
                ] );

                return false;
            }
        }

        /**
         * rollback
         * 
         * Roll back the current transaction
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if transaction rolled back successfully
         */
        public function rollback( ) : bool {

            // try to rollback transaction
            try {

                // rollback the transaction
                return $this -> db_handle -> rollBack( );

            // whoopsie...
            } catch ( \Exception $e ) {

                // error logging
                LOG::error( "Database Transaction Rollback Error", [
                    'message' => $e -> getMessage( )
                ] );

                return false;
            }
        }

        /**
         * reset
         * 
         * Reset the query builder state
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return self Returns self for method chaining
         */
        public function reset( ) : self {

            // reset all query builder properties
            $this -> current_query = '';
            $this -> query_params = [];
            $this -> fetch_mode = \PDO::FETCH_OBJ;
            $this -> fetch_single = false;

            // debug logging
            LOG::debug( "Database Reset Completed" );
            
            // return self for chaining
            return $this;
        }

        /** 
         * bind_params
         * 
         * Bind parameters to a prepared statement with appropriate data types
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param PDOStatement $stmt The prepared statement to bind parameters to
         * @param array $params The parameters to bind
         * @return void
         */
        private function bind_params( \PDOStatement $stmt, array $params = [] ): void {

            // if we don't have any parameters just return
            if ( empty( $params ) ) {
                LOG::debug( "Database Bind Params - No Parameters to Bind" );
                return;
            }

            // try to bind parameters
            try {

                // loop over the parameters
                foreach ( $params as $i => $param ) {

                    // Always bind as string for regex fields
                    if ( is_string( $param ) && preg_match( '/[\[\]{}()*+?.,\\^$|#\s-]/', $param ) ) {
                        $stmt -> bindValue( $i + 1, $param, \PDO::PARAM_STR );

                        // debug logging
                        LOG::debug( "Database Parameter Bound (Regex String)", [
                            'index' => $i + 1,
                            'pdo_type' => 'PDO::PARAM_STR',
                        ] );

                        continue;
                    }

                    // match the parameter types
                    $paramType = match ( strtolower( gettype( $param ) ) ) {
                        'boolean' => \PDO::PARAM_BOOL,
                        'integer' => \PDO::PARAM_INT,
                        'null' => \PDO::PARAM_NULL,
                        default => \PDO::PARAM_STR
                    };
                    
                    // bind the parameter and value
                    $stmt -> bindValue( $i + 1, $param, $paramType );

                    // debug logging
                    LOG::debug( "Database Parameter Bound", [
                        'index' => $i + 1,
                        'param_type' => gettype( $param ),
                        'pdo_type' => $paramType,
                    ] );
                }

                // debug logging
                LOG::debug( "Database Bind Params Completed Successfully", [
                    'total_bound' => count( $params )
                ] );

            // whoopsie...
            } catch ( \Exception $e ) {

                // error logging
                LOG::error( "Database Bind Params Error", [
                    'message' => $e -> getMessage( ),
                ] );

                throw $e;
            }
        }

        /**
         * raw
         * 
         * Execute a raw query without the query builder
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $query The SQL query to execute
         * @param array $params Optional parameters to bind
         * @return mixed Returns query results or false on failure
         */
        public function raw( string $query, array $params = [] ) : mixed {

            // try to execute the raw query
            try {
            
                // prepare the statement
                $stmt = $this -> db_handle -> prepare( $query );
                
                // bind parameters if we have any
                $this -> bind_params( $stmt, $params );
                
                // execute the query, if it fails, log an error and return false
                if ( ! $stmt -> execute( ) ) {

                    // error logging
                    LOG::error( "Database Raw Query Execution Failed", [] );
                    return false;
                }
                
                // determine query type
                $query_type = strtoupper( substr( trim( $query ), 0, 6 ) );
                
                // handle SELECT queries
                if ( $query_type === 'SELECT' ) {
                    $results = $stmt -> fetchAll( \PDO::FETCH_OBJ );
                    $stmt -> closeCursor( );

                    // debug logging
                    LOG::debug( "Database Raw SELECT Results", [
                        'has_results' => ! empty( $results ),
                        'result_count' => is_array( $results ) ? count( $results ) : 0
                    ] );

                    // return the results
                    return ! empty( $results ) ? $results : false;
                }
                
                // handle INSERT queries
                if ( $query_type === 'INSERT' ) {
                    $id = $this -> db_handle -> lastInsertId( );
                    $result = $id ?: true;

                    // debug logging
                    LOG::debug( "Database Raw INSERT Results", [] );

                    // return the result
                    return $result;
                }
                
                // handle UPDATE/DELETE queries
                if ( in_array( $query_type, ['UPDATE', 'DELETE'] ) ) {
                    $affected_rows = $stmt -> rowCount( );

                    // debug logging
                    LOG::debug( "Database Raw {$query_type} Results", [] );

                    // return the result
                    return $affected_rows;
                }
                
                // return true for other successful queries
                return true;

            // whoopsie...
            } catch ( \Exception $e ) {

                // error logging
                LOG::error( "Database Raw Query Error", [
                    'message' => $e -> getMessage( ),
                ] );

                // throw the exception
                throw $e;
            }
        }
    
    }

}