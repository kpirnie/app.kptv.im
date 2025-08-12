<?php
/**
 * This is our database class
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 * 
 */

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
        protected ?PDO $db_handle = null;

        // query builder properties
        protected string $current_query = '';
        protected array $query_params = [];
        protected int $fetch_mode = PDO::FETCH_OBJ;
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
       
            // get our database settings
            $db_settings = KPT::get_setting( 'database' );

            // build the dsn string
            $dsn = "mysql:host={$db_settings -> server};dbname={$db_settings -> schema}";
            
            // setup the PDO connection
            $this -> db_handle = new PDO( $dsn, $db_settings -> username, $db_settings -> password );

            // Set character encoding
            $this -> db_handle -> exec( "SET NAMES {$db_settings -> charset} COLLATE {$db_settings -> collation}" );
            $this -> db_handle -> exec( "SET CHARACTER SET {$db_settings -> charset}" );
            $this -> db_handle -> exec( "SET collation_connection = {$db_settings -> collation}" );

            // set pdo attributes
            $this -> db_handle -> setAttribute( PDO::ATTR_EMULATE_PREPARES, false );
            $this -> db_handle -> setAttribute( PDO::ATTR_PERSISTENT, true );
            $this -> db_handle -> setAttribute( PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true );
            $this -> db_handle -> setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
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

            // reset
            $this -> reset( );

            // close the connection
            $this -> db_handle = null;

            // clear em our
            unset( $this -> db_handle );
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
            $this -> fetch_mode = PDO::FETCH_ASSOC;
            
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
            $this -> fetch_mode = PDO::FETCH_OBJ;
            
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
                throw new RuntimeException( 'No query has been set. Call query() first.' );
            }
            
            // if limit is provided, determine fetch mode
            if ( $limit === 1 ) {

                // set the single property
                $this -> fetch_single = true;
            
            // otherwise
            } elseif ( $limit > 1 ) {

                // set it false
                $this -> fetch_single = false;
            }
            
            // prepare the statement
            $stmt = $this -> db_handle -> prepare( $this -> current_query );
            
            // bind parameters if we have any
            $this -> bind_params( $stmt, $this -> query_params );
            
            // execute the query
            if ( ! $stmt -> execute( ) ) {
                return false;
            }
            
            // fetch based on mode
            if ( $this -> fetch_single ) {

                // fetch only one record
                $result = $stmt -> fetch( $this -> fetch_mode );

                // close the cursor
                $stmt -> closeCursor( );

                // return the result
                return ! empty( $result ) ? $result : false;
            
            } else {
            
                // fetch all records
                $results = $stmt -> fetchAll( $this -> fetch_mode );

                // close the cursor
                $stmt -> closeCursor( );

                // return the resultset
                return ! empty( $results ) ? $results : false;
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
                throw new RuntimeException( 'No query has been set. Call query() first.' );
            }
            
            // prepare the statement
            $stmt = $this -> db_handle -> prepare( $this -> current_query );
            
            // bind parameters if we have any
            $this -> bind_params( $stmt, $this -> query_params );
            
            // execute the query
            $success = $stmt -> execute( );
            
            if ( ! $success ) {
                return false;
            }
            
            // determine return value based on query type
            $query_type = strtoupper( substr( trim( $this -> current_query ), 0, 6 ) );
            
            // figure out what kind of query are we running for the return value
            switch ( $query_type ) {
                case 'INSERT':

                    // return last insert ID for inserts
                    $id = $this -> db_handle -> lastInsertId( );
                    return $id ?: true;
                    
                case 'UPDATE':
                case 'DELETE':

                    // return affected rows for updates/deletes
                    return $stmt -> rowCount( );
                    
                default:
                    // return success for other queries
                    return $success;
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
            
            // return the last id
            return $this -> db_handle -> lastInsertId( );
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
            
            return $this -> db_handle -> beginTransaction( );
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
            
            return $this -> db_handle -> commit( );
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
            
            return $this -> db_handle -> rollBack( );
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
            $this -> fetch_mode = PDO::FETCH_OBJ;
            $this -> fetch_single = false;
            
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
        private function bind_params( PDOStatement $stmt, array $params = [] ): void {

            // if we don't have any parameters just return
            if ( empty( $params ) ) return;

            // loop over the parameters
            foreach ( $params as $i => $param ) {

                // Always bind as string for regex fields
                if ( is_string( $param ) && preg_match( '/[\[\]{}()*+?.,\\^$|#\s-]/', $param ) ) {
                    $stmt -> bindValue( $i + 1, $param, PDO::PARAM_STR );
                    continue;
                }

                // match the parameter types
                $paramType = match ( strtolower( gettype( $param ) ) ) {
                    'boolean' => PDO::PARAM_BOOL,
                    'integer' => PDO::PARAM_INT,
                    'null' => PDO::PARAM_NULL,
                    default => PDO::PARAM_STR
                };
                
                // bind the parameter and value
                $stmt -> bindValue( $i + 1, $param, $paramType );

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
            
            // prepare the statement
            $stmt = $this -> db_handle -> prepare( $query );
            
            // bind parameters if we have any
            $this -> bind_params( $stmt, $params );
            
            // execute the query
            if ( ! $stmt -> execute( ) ) {
                return false;
            }
            
            // determine query type
            $query_type = strtoupper( substr( trim( $query ), 0, 6 ) );
            
            // handle SELECT queries
            if ( $query_type === 'SELECT' ) {
                $results = $stmt -> fetchAll( PDO::FETCH_OBJ );
                $stmt -> closeCursor( );
                return ! empty( $results ) ? $results : false;
            }
            
            // handle INSERT queries
            if ( $query_type === 'INSERT' ) {
                $id = $this -> db_handle -> lastInsertId( );
                return $id ?: true;
            }
            
            // handle UPDATE/DELETE queries
            if ( in_array( $query_type, ['UPDATE', 'DELETE'] ) ) {
                return $stmt -> rowCount( );
            }
            
            // return true for other successful queries
            return true;
        }
    
    }

}
