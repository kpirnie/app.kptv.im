<?php
/**
 * KPT_DB
 * 
 * This is our database class
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Tasks
 * 
 */
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// if the class is not already in userspace
if( ! class_exists( 'KPT_DB' ) ) {

    /** 
     * Class KPT_DB
     * 
     * Database Class
     * 
     * @since 8.4
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Tasks
     * 
     * @property protected $db_handle: The database handle used throughout the class
     * 
     */
    class KPT_DB {

        // hold the database handle object
        protected ?PDO $db_handle = null;

        // fire us up
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
            $this -> db_handle->setAttribute( PDO::ATTR_EMULATE_PREPARES, false );
            $this -> db_handle->setAttribute( PDO::ATTR_PERSISTENT, true );
            $this -> db_handle->setAttribute( PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true );
            $this -> db_handle->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
        }

        // destroy the class usage and nullify the handle
        public function __destruct( ) {

            // close the connection
            $this -> db_handle = null;
        }

        /** 
         * execute
         * 
         * Execute a query against our database
         * 
         * @param string $query The SQL query to be executed
         * @param array $params An array of parameters to bind to the query
         * 
         * @return mixed Last inserted ID or boolean success status
         */
        public function execute( string $query, array $params = [] ): mixed {
            
            // setup our statement
            $stmt = $this -> db_handle -> prepare( $query );

            // bind the parameters
            $this -> bind_params( $stmt, $params );

            // execute it
            $success = $stmt -> execute( );

            // Return last inserted ID for inserts, true for other successful operations
            $ret = $success ? $this -> db_handle -> lastInsertId( ) : false;

            // Return ID if exists, otherwise return success bool
            return $ret ?: $success;
        }

        /** 
         * select_single
         * 
         * Run a select statement returning a single record
         * 
         * @param string $query The SQL query to execute
         * @param array $params Parameters to bind to the query
         * 
         * @return object|bool stdClass object or false if nothing returned
         */
        public function select_single( string $query, array $params = [] ): object|bool {

            // setup the statment
            $stmt = $this -> db_handle -> prepare( $query );

            // bind the parameters
            $this -> bind_params( $stmt, $params );

            // if we get nothing from executing the query
            if ( ! $stmt -> execute( ) ) {
                
                // return false
                return false;
            }
            
            // hold the results
            $result = $stmt -> fetch( PDO::FETCH_OBJ );

            // close up our cursor
            $stmt -> closeCursor( );
            
            // return the resultset or false if nothing
            return ! empty( $result ) ? $result : false;
        }

        /** 
         * select_many
         * 
         * Run a select statement returning multiple records
         * 
         * @param string $query The SQL query to execute
         * @param array $params Parameters to bind to the query
         * 
         * @return array|bool Array of stdClass objects or false if nothing returned
         */
        public function select_many( string $query, array $params = [] ): array|bool {

            // setup the statment
            $stmt = $this -> db_handle -> prepare( $query );

            // bind the parameters
            $this -> bind_params( $stmt, $params );
            
            // if we get nothing from executing the query
            if ( ! $stmt -> execute( ) ) {

                // return false
                return false;
            }
            
            // setup the results
            $results = $stmt -> fetchAll (PDO::FETCH_OBJ );

            // close the cursor
            $stmt -> closeCursor( );
            
            // return the resultset or false if nothing
            return ! empty( $results ) ? $results : false;
        }

        /** 
         * bind_params
         * 
         * Bind parameters to a prepared statement
         * 
         * @param PDOStatement $stmt The prepared statement
         * @param array $params The parameters to bind
         * 
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
    
    }

}
