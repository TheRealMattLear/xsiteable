<?php

    namespace xs\TopicMaps ;
    
    require_once ( XS_DIR_LIB . '/spyc/spyc.php5' ) ;

    class Datamodel {

            private $_db = null ;
            private $timer = null ;

            private $_modelFilename = 'application/datastore/datamodel.dbml' ;
            private $_dataFilename = 'application/datastore/data.dbml' ;
            private $_modelArr = null ;
            private $_dataArr = null ;

            private $db_injected_handler = null ;

            function __construct ( &$db ) {

                    $this->_db = $db ;

                    // var_dump ( $db ) ;

                    $this->_modelArr = \Spyc::YAMLLoad ( $this->_modelFilename ) ;
                    $this->_dataArr = \Spyc::YAMLLoad ( $this->_dataFilename ) ;

            }

            function getData () {

                    if ( isset ( $this->_dataArr['db_data'] ) )
                            return $this->_dataArr['db_data'] ;
                    else
                            return array () ;

            }

            function getDataModel () {

                    if ( isset ( $this->_modelArr['db_tables'] ) )
                            return $this->_modelArr['db_tables'] ;
                    else
                            return array () ;
            }

            function SQLfields ( $fields, $type = '' ) {

                    $ids = '' ;

                    foreach ( $fields as $i => $v )
                            $ids .= $i . $type . ", " ;

                    $ids = substr ( $ids, 0, strlen ($ids) - 2 ) ;

            }

            function SQLtype ( $type ) {
                    switch ( $type ) {
                            case 'int'         : return 'INTEGER' ; break ;
                            case 'int.primary' : return 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' ; break ;
                            case 'text.mini'   : return 'VARCHAR(50)' ; break ;
                            case 'text.short'  : return 'VARCHAR(100)' ; break ;
                            case 'text'        : return 'VARCHAR(200)' ; break ;
                            case 'text.medium' : return 'VARCHAR(2000)' ; break ;
                            case 'text.long'   : return 'VARCHAR(50000)' ; break ;
                            case 'blob'        : return 'LONGTEXT' ; break ;
                            case 'time'        : return 'TIMESTAMP' ; break ;
                            case 'truefalse'   : return 'VARCHAR(1)' ; break ;
                            case 'toggle'      : return 'VARCHAR(1)' ; break ;
                            default            : return 'VARCHAR(20)' ; break ;
                    }
            }

            function prepareArray ( $model, $specific_table = false ) {

                    $res = array() ;

                    foreach ( $model as $table => $fields ) {

                            foreach ( $fields as $field => $value ) {

                                    $parse = explode ( ' ', $value ) ;
                                    $type  = $parse[0] ; $index = false ; $rel   = false ;

                                    // echo "[".substr ( $field, 0, 1 )."] " ;
                                    if ( substr ( $field, 0, 1 ) != '_' ) {

                                            if ( isset ( $parse[1] ) ) {
                                                    if ( $parse[1] == '*' )
                                                            $index = true ;
                                                    else
                                                            $rel = $parse[1] ;
                                                    if ( isset ( $parse[2] ) ) {
                                                            if ( $parse[2] == '*' )
                                                                    $index = true ;
                                                            else
                                                                    $rel = $parse[2] ;
                                                    }
                                            }

                                            $this_table = $specific_table ;

                                            if ( $specific_table == false )
                                                    $this_table = $table ;

                                            if ( $this_table == $table ) {

                                                    $res[$this_table][$field]['type'] = $type ;
                                                    if ( $index ) $res[$this_table][$field]['index'] = true ;
                                                    if ( $rel   ) $res[$this_table][$field]['rel'] = $rel ;

                                            }
                                    }

                            }
                    }

                    return $res ;

            }

            function prepareDataArray ( $model, $specific_table = false ) {

                    if ( $specific_table == false )
                            return $model ;
                    else {
                            if ( isset ( $model[$specific_table] ) )
                                    return array ( $specific_table => $model[$specific_table] ) ;
                            else
                                    return array () ;
                    }
            }

            function prepareSQL ( $res, $delete = false, $specific_table = false ) {

                    $ret = array() ;

                    if ( $delete )
                        foreach ( $res as $table => $fields ) {
                                $sql = "DROP TABLE IF EXISTS $table ;" ;
                                $ret[] = $sql ;
                        }

                    foreach ( $res as $table => $fields ) {

                            $sql = "CREATE TABLE IF NOT EXISTS $table ( " ;

                            foreach ( $fields as $field => $values )
                                    $sql .= $field . " " . $this->SQLtype ( $values['type'] ) . ", " ;

                            $sql = substr ( $sql, 0, strlen ($sql) - 2 ) ;

                            $sql .= " ) ENGINE=innodb ;" ;

                            $ret[] = $sql ;

                    }

                    foreach ( $res as $table => $fields ) {

                            $hasIndex = false ;
                            $idx = "CREATE INDEX IF NOT EXISTS idx_$table ON $table ( " ;

                            foreach ( $fields as $field => $values ) {
                                    if ( isset ( $values['index'] ) ) {
                                            $idx .= $field . ", " ;
                                            $hasIndex = true ;
                                    }
                            }

                            $idx = substr ( $idx, 0, strlen ($idx) - 2 ) ;
                            $idx .= " ) ; \n" ;

                            if ( $hasIndex )
                                    $ret[] = $idx ;
                    }

                    // $ret[] = "ALTER TABLE xs_topic CHANGE status topicmap INT( 11 ) NULL DEFAULT '1' ;" ;

                    return $ret ; 

            }

            function prepareDataSQL ( $res ) {

                $who = '99' ;

                    $ret = array() ;

                    foreach ( $res as $table => $meta ) {

                            $count = 0 ;
                            if ( isset ( $meta['values'] ) )
                                    $count = count ( $meta['values'] ) ;

                            if ( $count > 0 ) {

                                    for ( $n=0; $n<$count; $n++ ) {

                                            $sql = "INSERT INTO $table ( " ;

                                            if ( isset ( $meta['into'] ) ) {

                                                // $meta['into'][] = 'm_p_who' ;

                                                    foreach ( $meta['into'] as $field )
                                                            $sql .= $field . ", " ;

                                                    $sql .= 'm_p_who, m_c_who' ;

                                                    // $sql = substr ( $sql, 0, strlen ($sql) - 2 ) ;

                                            }

                                            $sql .= " ) VALUES ( " ;

                                            if ( isset ( $meta['values'] ) ) {

                                                foreach ( $meta['values'][$n] as $idx => $value ) {
                                                        $s = trim ( substr ( $value, 0, 8) ) ;
                                                        if ( $s == '%base64=' ) {
                                                                $str = $this->_db->quote (
                                                                        html_entity_decode (
                                                                                base64_decode (
                                                                                        substr ($value, 8)
                                                                                ), ENT_COMPAT, 'UTF-8'
                                                                        )
                                                                ) ;
                                                                $str = str_replace ( "&~", "&#", $str ) ;
                                                                $str = str_replace ( " & ", " &amp; ", $str ) ;
                                                                $sql .= $str.", " ;
                                                                // echo "[$idx=".substr ( $value, 0, 8)."]" ;
                                                        } else {
                                                            $sql .= "'".html_entity_decode($value)."', " ;
                                                        }
                                                }

                                                // $sql = substr ( $sql, 0, strlen ($sql) - 2 ) ;

                                                $sql .= " '$who', '$who' " ;
                                                // echo "($sql) <br/>" ;
                                            }

                                            $sql .= " ) ;" ;
                                            $ret[] = $sql ;

                                    }
                            }
                    }

                    return $ret ;

            }

            function createOntology () {


                // Our input XML stack as a DOM object
                try {
                    $owl = new \DOMDocument() ;
                    $owl->load ( './application/datastore/gist.xml', LIBXML_COMPACT ) ;
                    echo ( "Render: XML OK" ) ;
                } catch ( exception $ex ) {
                    echo ( "Render: XML failed. Not well-formed?" ) ;
                }

                // The XSLT stylesheet as a DOM object
                try {
                    $xsl = new \DOMDocument() ;
                    $xsl->load ( "./application/templates/gist.xsl", LIBXML_COMPACT ) ;
                    echo ( "Render: XSLT files load OK" ) ;
                } catch ( exception $ex ) {
                    echo ( "Render: XSLT files load failed. Not well-formed?" ) ;
                }

                // Create an xSLT processor
                try {
                    $proc = new \XSLTProcessor() ;
                    $proc->importStyleSheet ( $xsl ) ;
                    echo ( "Render: XSLT import" ) ;
                    $proc->registerPHPFunctions() ;
                    echo ( "Render: registration and parameters OK" ) ;
                } catch ( exception $ex ) {
                    echo ( "Render: XSLT processor setup and parameters FAILED. Huh?" ) ;
                }

                $res = '' ;

                // Transform the XSLT
                try {
                    $res = $proc->transformToXML ( $owl ) ;
                    echo ( "Render: Transform OK" ) ;
                } catch ( exception $ex ) {
                    echo ( "Render: XSLT transform FAILED. Hoo-boy." ) ;
                }

                $final = (string) $res ;

                // $res = $res[0] ;

                // print_r ( $res ) ;
                // var_dump ( $res ) ;


                // echo "<hr/> $res <hr />" ;



                $model = $this->getDataModel() ;
                $data  = $this->getData() ;

                $datamodelStruct = $this->prepareArray ( $model, false ) ;
                $dataStruct      = $this->prepareDataArray ( $data, false ) ;

                $res = array () ;
                foreach ( $dataStruct['xs_topic']['values'] as $item ) {
                    $id = $item[0] ;
                    $name = $item[1] ;
                    $res[$name] = $id ;
                }

                $fin = $a = '' ;
                $fin .= "<?php\n\n   /* Don't edit in this file; it will get overwritten from time to time */ \n\n" ;
                foreach ( $res as $name => $id ) {
                    $fin .= "   define ( '__".strtoupper($name)."', '$id' ) ; \n" ;
                }

                // foreach ( $res as $name => $id ) {
                //     $a .= "   define ( '__".strtoupper($name)."', '$id' ) ; \n" ;
                // }

                return $fin . "\n\n" . $final ; // . ' define ( "__HAS_ACCESS", "100" ) ; ' ;
            }

            function backupData ( $path ) {

                $dumpSettings = array(
                    'include-tables' => array('xs_topic', 'xs_property', 'xs_assoc', 'xs_assoc_member', 'xs_topicmap' ),
                    'exclude-tables' => array('xs_content', 'xs_meta', 'xs_nugget', 'xs_psi' ),
                    'compress' => true,
                    'no-data' => false,             /* http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_no-data */
                    'add-drop-table' => false,      /* http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_add-drop-table */
                    'single-transaction' => true,   /* http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_single-transaction */
                    'lock-tables' => false,         /* http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_lock-tables */
                    'add-locks' => true,            /* http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_add-locks */
                    'extended-insert' => true       /* http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_extended-insert */
                );
                $dump = new \MySQLDump();
                $dump->inject_db_handler ( $this->_db ) ;
                $dump->settings($dumpSettings);
                $dump->start($path);

            }

            function restoreData ( $input ) {

                echo "<pre style='background-color:yellow;'> " ; echo $input ; echo " </pre>" ;

            }

            function installModel ( $delete = false, $specific_table = false ) {

                    $debug = false;

                    // $this->timer->add ( 'get:datamodel' ) ;

                    $model = $this->getDataModel() ;

                    // if ( $debug ) 	echo "<pre>".print_r ( $model, true )."</pre><hr>" ;

                    // $this->timer->add ( 'get:data' ) ;

                    $data  = $this->getData() ;

                    // $this->timer->add ( 'create:SQL' ) ;

                    $presql = array() ;
                    $realsql = array() ;

                    // if ( $specific_table !== false ) {

                            // $presql[] = "DROP INDEX 'idx_".$specific_table."' ;" ;
                    // }

                    $datamodelStruct = $this->prepareArray ( $model, $specific_table ) ;
                    $dataStruct      = $this->prepareDataArray ( $data, $specific_table ) ;

                    // if ( $debug ) echo "<pre>". print_r ( $datamodelStruct, true )."</pre><hr>" ;

                    $realsql = $this->prepareSQL ( $datamodelStruct, $delete ) ;
                    $sqlData = array () ;

                    if ( $delete !== false )
                        $sqlData = $this->prepareDataSQL ( $dataStruct ) ;

                    // if ( $debug ) echo "<pre>". print_r ( $realsql, true )."</pre><hr>" ;

                    $sql = array_merge ( $presql, $realsql ) ;
                    // $sql = $presql + $realsql ;

                    // if ( $debug ) echo "<pre style='background-color:#def;'>". print_r ( $sql, true )."</pre><hr>" ;

                    // $this->timer->add ( 'query:SQL:datamodel' ) ;

                    try {

                        if ( !$debug) $this->_db->beginTransaction();
                        foreach ( $sql as $num => $line ) {
                            $result = null ;
                            try {
                                    if ( $debug )
                                        var_dump ( $line ) ;
                                    else
                                        $result = $this->_db->query ( $line ) ;
                                    echo "<pre>[$num] ".print_r ( $line, true )." [".print_r ( $result, true )."]</pre><hr>" ;

                            } catch ( Exception $e ) {
                                        echo "<pre style='background-color:red;color:yellow;'> error : ".print_r ( $result, true )."</pre><hr>" ;
                            }
                        }
                        if ( !$debug) { $result = $this->_db->commit(); print_r ( $result ) ; print_r ( $this->_db ) ; }
                    } catch ( Exception $e ) {
                        echo "!!!" ;
                        print_r ( $e ) ;
                    }


                    $sqlData[] = "INSERT INTO xs_meta (name, value) VALUES ( '_struct', '".serialize ( $datamodelStruct ) ."') ; " ;


                    // Look for table types
                    // echo "<pre style='background-color:yellow;'>". print_r ( $model, true )."</pre><hr>" ;

                    foreach ( $model as $table => $fields ) {
                            foreach ( $fields as $field => $value ) {
                                    if ( substr ( $field, 0, 1 ) == '_' ) {
                                            $name = substr ( $field, 1 ) ;
                                            $sqlData[] = "INSERT INTO xs_meta (name, value) VALUES ( '$table', '$value') ; " ;
                                    }
                            }
                    }

                    try {

                        // $result = $this->_db->query ( "SELECT * FROM _meta WHERE name='_struct' ; " ) ;
                        if ( !$debug) $this->_db->beginTransaction();
                        foreach ( $sqlData as $x => $line ) {
                            if ( $debug )
                                echo "<pre>$x : ".print_r ( $line, true )."</pre><hr>" ;
                            else {
                                $result = $this->_db->query ( $line ) ;
                                echo "<pre>[$x] ".print_r ( $line, true )."</pre><hr>" ;
                            }
                        }

                        if ( !$debug) $this->_db->commit();

                    } catch ( Exception $e ) {
                        echo "!!!" ;
                        print_r ( $e ) ;
                    }


            }


            function fallateRows ( $input ) {
                    $res = array() ;
                    foreach ( $input as $idx => $val )
                            $res[$val['topic_id']][$val['type_id']] = $val['value'] ;
                    return $res ;
            }

            // Helper function to trim a string

            function trimall($str, $charlist = "\t\n\r") {
                return preg_replace('/\s+/', ' ', $str) ;
            }

            // Clean a string, what can I say?

            function clean_tag ( $tag ) {
                    return 	strtolower (
                            trim (
                                    str_ireplace ( ' ', '_',
                                            $this->trimall ( $tag )
                                    )
                            )
                    ) ;
            }

            function update_records ( $table, $arr ) {

                    $this->start_transaction() ;

                    foreach ( $arr as $idx => $record ) {

                            $sql = "insert into ".$table." ( " ;

                            $count = -1 ;

                            foreach ( $record as $idx=>$value ) {

                                    if ( $count != -1 )
                                            $sql .= ", " ;

                                    $sql .= $idx ;

                                    $count++ ;

                            }

                            // echo $sql." ) >> " ;

                            $sql .= " ) values ( " ;

                            $count = -1 ;

                            foreach ( $record as $idx=>$value ) {

                                    $value = $this->trimall ( $value ) ;

                                    if ( $count != -1 )
                                            $sql .= ", " ;

                                    $sql .= $this->_db->quote ( $value ) ;

                                    $count++ ;

                                    // printf( "\n %12s = '", $idx ) ;

                                    // echo  htmlentities ( $value ) . "' " ;

                            }

                            $sql .= " ) " ;

                            // echo "\n\n" ;

                            $this->generic_query ( 'update'.$idx, $sql ) ;

                            // $db->query ( $sql ) ;
                            // echo $sql ;

                    }

                    $this->end_transaction() ;

            }

            // Small anonymous function for returning topics
            function t ( $type = '', $name = '', $psi = '' ) {
                    $arr = array () ;
                    if ( $type != '' ) $arr['type'] = $type ;
                    if ( $name != '' ) $arr['name'] = $name ;
                    if ( $psi  != '' ) $arr['psi']  = $psi ;
                    $arr['property'] = array() ;
                    return $arr ;
            }

            function validate ( $arr, $idx, $value ) {
                    switch ( $idx ) {
                            case 'type' :
                                    if ( isset ( $arr[$value] ) )
                                            return $value ;
                                    else
                                            return "*** Non-valid '$value'" ;
                                    break ;
                            default:
                                    break;
                    }
            }

            function firstAlphaPos ( $string ) {
                    for ( $n=0; $n<strlen($string); $n++ )
                            if ( !ctype_space ( $string[$n] ) )
                                    return $n ;
                    return false ;
            }

            function pickParts ( $string ) {
                    $t = trim ( $string ) ;
                    $e = explode ( " ", $t ) ;
                    $ret = array() ;
                    if ( isset ( $e[0] ) ) $ret['token'] = $e[0] ;
                    if ( isset ( $e[1] ) ) $ret['type']  = $e[1] ;

                    return $ret ;
            }

            function parseMapNotation ( $notation ) {

                    $ret = '' ;
                    $pos_prev = 0 ;
                    $level = 1 ;
                    $topiccount = -1 ;
                    $topics = array() ;
                    $topicid = '' ;

                    foreach ( $notation as $num => $line ) {

                            $pos = $this->firstAlphaPos ( $line ) ;

                            if ( $pos > $pos_prev )
                                    $level++ ;
                            if ( $pos < $pos_prev )
                                    $level-- ;

                            $ret .= "[$level]$line ($pos)" ;

                            $parts = $this->pickParts ( $line ) ;

                                    echo "\n\n[$line]\n\n" ;
                            if ( $parts['token'] == 'topic' ) {
                                    $topiccount++ ;
                                    $topicid = $parts['type'] ;
                                    $topics[$topicid] = array() ;
                            }

                            if ( $parts['token'] == 'property' ) {
                                    $topics[$topicid]['property'][$parts['type']] = strstr ( strstr ($line, "'"), "'" ) ;
                            }

                            if ( $parts['token'] == 'name' ) {
                                    $topics[$topicid]['name'] = strstr ( strstr ($line, "'"), "'" ) ;
                            }

                            // $ret .= "[$idx : $part] " ;

                            $ret .= "\n" ;

                            $pos_prev = $pos ;
                    }

                    return $topics ;

            }

    }

