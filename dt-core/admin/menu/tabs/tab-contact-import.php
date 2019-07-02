<?php

if ( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class Disciple_Tools_Contact_Import_Tab
 */
class Disciple_Tools_Contact_Import_Tab extends Disciple_Tools_Abstract_Menu_Base
{
    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    /**
     * Constructor function.
     *
     * @access  public
     * @since   0.1.0
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_submenu' ], 99 );
        add_action( 'dt_utilities_tab_menu', [ $this, 'add_tab' ], 50, 1 ); // use the priority setting to control load order
        add_action( 'dt_utilities_tab_content', [ $this, 'content' ], 99, 1 );


        parent::__construct();
    } // End __construct()


    public function add_submenu() {
        add_submenu_page( 'dt_utilities', __( 'Import Contacts', 'disciple_tools' ), __( 'Import Contacts', 'disciple_tools' ), 'manage_dt', 'dt_utilities&tab=contact-import', [ 'Disciple_Tools_Utilities_Menu', 'content' ] );
    }

    public function add_tab( $tab ) {
        echo '<a href="'. esc_url( admin_url() ).'admin.php?page=dt_utilities&tab=contact-import" class="nav-tab ';
        if ( $tab == 'contact-import' ) {
            echo 'nav-tab-active';
        }
        echo '">'. esc_attr__( 'Import Contacts', 'disciple_tools' ) .'</a>';
    }

    public function content( $tab ) {
        if ( 'contact-import' == $tab ) {
            self::template( 'begin' );
            $this->tools_box_message();
            self::template( 'right_column' );
            $this->instructions();
            self::template( 'end' );
        }
    }

    public function instructions() {
        $this->box( 'top', 'Required Format' );
        ?>

        <p><?php esc_html_e( "use utf-8 file format", 'disciple_tools' ) ?></p>       
        <?php
        $this->box( 'bottom' );
    }


    //tools page
    public function tools_box_message() {
        //$this->box( 'top', 'CSV Import Contacts (Modified)' );
        $this->box( 'top', 'Import Contacts' );
        //check if it can run commands
        $run = true;
        //check for admin
        if ( ! is_admin() ) {
            $run = false;
        }

        //check for action of csv import
        if ( isset( $_POST['csv_import_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csv_import_nonce'] ) ), 'csv_import' ) && $run ) {
            //@codingStandardsIgnoreLine
            if ( isset( $_FILES[ "csv_file" ] ) ) {
                //@codingStandardsIgnoreLine
                $file_parts = explode( ".", sanitize_text_field( wp_unslash( $_FILES[ "csv_file" ][ "name" ] ) ) )[ count( explode( ".", sanitize_text_field( wp_unslash( $_FILES[ "csv_file" ][ "name" ] ) ) ) ) - 1 ];
                if ( $_FILES["csv_file"]["error"] > 0 ) {
                    esc_html_e( "ERROR UPLOADING FILE", 'disciple_tools' );
                    ?>
                    <form id="back" method="post" enctype="multipart/form-data">
                        <a href="" class="button button-primary"> <?php esc_html_e( "Back", 'disciple_tools' ) ?> </a>
                    </form>
                    <?php
                    exit;
                }
                if ( $file_parts != 'csv' ) {
                    esc_html_e( "NOT CSV", 'disciple_tools' );
                //if ( $file_parts != 'tsv' ) {
                //    esc_html_e( "NOT TSV", 'disciple_tools' );
                    ?>
                    <form id="back" method="post" enctype="multipart/form-data">
                        <a href="" class="button button-primary"> <?php esc_html_e( "Back", 'disciple_tools' ) ?> </a>
                    </form>
                    <?php
                    exit;
                }
                //@codingStandardsIgnoreLine
                if ( mb_detect_encoding( file_get_contents( $_FILES[ "csv_file" ][ 'tmp_name' ], false, null, 0, 100 ), 'UTF-8', true ) === false ) {
                    esc_html_e( "FILE IS NOT UTF-8", 'disciple_tools' );
                    ?>
                    <form id="back" method="post" enctype="multipart/form-data">
                        <a href="" class="button button-primary"> <?php esc_html_e( "Back", 'disciple_tools' ) ?> </a>
                    </form>
                    <?php
                    exit;
                }
                //@codingStandardsIgnoreLine
                $this->import_csv( $_FILES[ 'csv_file' ], 
                        /** sanitize_text_field( wp_unslash( $_POST[ 'csv_del' ] ) ), */ ',',
                        sanitize_text_field( wp_unslash( $_POST[ 'csv_source' ] ) ), 
                        sanitize_text_field( wp_unslash( $_POST[ 'csv_assign' ] ) ), 
                        /** sanitize_text_field( wp_unslash( $_POST[ 'csv_header' ] ) ), */ "yes" );
            }
            exit;
        }        
        
        if ( isset( $_POST['csv_mappings_nonce'] ) 
		&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csv_mappings_nonce'] ) ), 'csv_mappings' ) 
		&& $run ) {         
            
            if ( isset( $_POST[ "csv_mapper" ], $_POST[ "csv_data" ]) ) {
                $mappingData = $_POST[ "csv_mapper" ];
                
                $valueMapperiData = isset($_POST['VMD'])?$_POST['VMD']:[];
                $valueMapperData = isset($_POST['VM'])?$_POST['VM']:[];
                
                //$mappingData = unserialize( base64_decode( $_POST[ "csv_mapper" ] ) );
                $csvData = unserialize( base64_decode( $_POST[ "csv_data" ] ) );
                $csvHeaders = unserialize( base64_decode( $_POST[ "csv_headers" ] ) );

                $this->mapping_mod($csvData, $csvHeaders, 
                        sanitize_text_field( wp_unslash( $_POST[ 'csv_del_temp' ] ) ), 
                        sanitize_text_field( wp_unslash( $_POST[ 'csv_source_temp' ] ) ), 
                        sanitize_text_field( wp_unslash( $_POST[ 'csv_assign_temp' ] ) ), 
                        $mappingData,
                        $valueMapperiData,
                        $valueMapperData);                
                exit;
            }            
        }
        
        
        //check for verification of data
        if ( isset( $_POST['csv_correct_nonce'] ) 
                && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csv_correct_nonce'] ) ), 'csv_correct' ) 
                && $run ) {
            //@codingStandardsIgnoreLine
            if ( isset( $_POST[ "csv_contacts" ] ) ) {            
                //@codingStandardsIgnoreLine
                $this->insert_contacts( unserialize( base64_decode( $_POST[ "csv_contacts" ] ) ) );                
            }
            exit;
        }
        ?>
            
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'csv_import', 'csv_import_nonce' ); ?>
            <table class="widefat">
                <tr>
                    <td>
                        <label for="csv_file"><?php esc_html_e( 'Select your csv file (comma seperated file)' ) ?></label><br>
                        <input class="button" type="file" name="csv_file" id="csv_file" />
                    </td>
                </tr>
                <tr style="display:none">
                    <td>
                        <label for="csv_multivalued"><?php esc_html_e( 'Does the file contain multivalues?' ) ?></label><br>
                        <?php /** <input class="checkbox" type="checkbox" name="csv_multivalued" id="csv_multivalued" onclick="setDel()" disabled="disabled" /> */ ?>
                        <input class="checkbox" type="checkbox" name="csv_multivalued" id="csv_multivalued" onclick="setDel()" disabled="disabled" checked="checked" />
                    </td>
                </tr>
                <tr style="display:none">
                    <td>
                        <label for="csv_del"><?php esc_html_e( "Add csv delimiter (default is fine)", 'disciple_tools' ) ?></label><br>
                        <?php /** <input type="text" name="csv_del" id="csv_del" value=',' size=2 />*/ ?>
                        <select name="csv_del" id="csv_del" onclick="deactMV()" disabled="disabled">
                            <option value="," selected="selected">, comma</option>
                            <option value="|">| tab</option>
                            <option value=";">; semi-colon</option>
                            <option value=":">: colon</option>
                            <option value="$">$ dollar</option>
                        </select>
                    </td>
                </tr>
                <tr style="display:none">
                    <td>
                        <label for="csv_header"><?php esc_html_e( "Does the file have a header? (i.e. a first row with the names of the columns?)", 'disciple_tools' ) ?></label><br>
                        <select name="csv_header" id="csv_header" readonly="readonly" disabled="disabled">
                            <option value=yes><?php esc_html_e( "yes", 'disciple_tools' ) ?></option>
                            <option value=no><?php esc_html_e( "no", 'disciple_tools' ) ?></option>
                        </select>

                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="csv_source">
                            <?php esc_html_e( "Where did these contacts come from? Add a source.", 'disciple_tools' ) ?>
                        </label><br>
                        <select name="csv_source" id="csv_source">
                            <?php
                            $site_custom_lists = dt_get_option( 'dt_site_custom_lists' );
                            foreach ( $site_custom_lists['sources'] as $key => $value ) {
                                if ( $value['enabled'] ) {
                                    ?>
                                    <option value=<?php echo esc_html( $key ); ?>><?php echo esc_html( $value['label'] ); ?></option>
                                    <?php
                                }
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="csv_assign">
                            <?php esc_html_e( "Which user do you want these assigned to?", 'disciple_tools' ) ?>
                        </label><br>
                        <select name="csv_assign" id="csv_assign">
                            <option value=""></option>
                            <?php
                            $args = [
                                'role__not_in' => [ 'registered' ],
                                'fields'       => [ 'ID', 'display_name' ],
                                'order'        => 'ASC',
                            ];
                            $users = get_users( $args );
                            foreach ( $users as $user ) { ?>
                                <option
                                    value=<?php echo esc_html( $user->ID ); ?>><?php echo esc_html( $user->display_name ); ?></option>
                            <?php } ?>
                        </select>

                    </td>
                </tr>
                <tr>
                    <td>
                        <p class="submit">
                            <input type="submit" 
                                   name="submit" 
                                   id="submit" 
                                   class="button"
                                   value=<?php esc_html_e( "Upload", 'disciple_tools' ) ?>>
                        </p>
                    </td>
                </tr>
            </table>
        </form>


        <?php /** <script>
        //function setDel(){
        //    if (document.getElementById('csv_multivalued').checked) {
        //        document.getElementById('csv_del').value = '|';
        //        //document.getElementById("csv_del").readOnly = true;
        //        document.getElementById("csv_del").setAttribute("readonly", true);
        //    } else {
        //        //document.getElementById("csv_del").readOnly = false;
        //        document.getElementById("csv_del").removeAttribute("readonly");
        //    }
        //}
        //
        //function deactMV(){
        //    document.getElementById('csv_multivalued').checked = false;
        //}
        </script> */ ?>

        <?php
        $this->box( 'bottom' );
    }
    
    public function mapping_mod($csvData, $csvHeaders, $del = ',', $source = 'web', $assign = '', $mappingData = [], $valueMapperiData = [], $valueMapperData = []) {     
        
        $conHeadersInfo = Disciple_Tools_Contacts::get_contact_header_info();  
        
        //remove unmapped data columns + header
        foreach((array)$mappingData as $_i=>$_d){ if($_d=='IGNORE'||$_d=='NONE'){ unset($mappingData[$_i]); } }
        //$proceed = true; $CD = []; for($_i=0;$_i<count((array)$mappingData);$_i++){
        //    $_v = $mappingData[$_i]; $_c = 0;
        //    foreach((array)$mappingData as $_j=>$_d){ if($_d == $_v){ $_c++; } }
        //    $CD[$_i] = $_c; 
        //}        
        //
        //foreach((array)$CD as $_v){ if($_v>1){ $proceed = $proceed && false; } }        
        //if(!$proceed){ die('Mapping Error! ERR'.__LINE__); }

        
//echo '$csvData<br/>'; echo '<pre>'; print_r($csvData); echo '</pre>'; echo '<hr/>';
//echo '$mappingData<br/>'; echo '<pre>'; print_r($mappingData); echo '</pre>'; echo '<hr/>';
//echo '$valueMapperiData<br/>'; echo '<pre>'; print_r($valueMapperiData); echo '</pre>'; echo '<hr/>';
//echo '$valueMapperData<br/>'; echo '<pre>'; print_r($valueMapperData); echo '</pre>'; echo '<hr/>';
//
//echo '$assign<br/>'; echo '<pre>'; print_r($assign); echo '</pre>'; echo '<hr/>';
//echo '$source<br/>'; echo '<pre>'; print_r($source); echo '</pre>'; echo '<hr/>';
//echo '$del<br/>'; echo '<pre>'; print_r($del); echo '</pre>'; echo '<hr/>';
//        
//echo '>>>'.__LINE__.'<br/>';
        
        $people = Disciple_Tools_Contacts::process_data($csvData, $mappingData, $valueMapperiData, $valueMapperData, $assign, $source, $del);
        
//echo 'Data:<br/>';
//echo '<pre>'; var_dump($people); echo '</pre>';
        
        //$html = Disciple_Tools_Contacts::display_data($people, $conHeadersInfo, $mappingData);
        $html = Disciple_Tools_Contacts::display_data2($people);
        echo $html;
        ?>     
        
        <form method="post" enctype="multipart/form-data">
            <?php // export form ?>
            <input type="hidden" name="csv_contacts" value="<?php echo esc_html( base64_encode( serialize( $people ) ) ); ?>">
            <?php wp_nonce_field( 'csv_correct', 'csv_correct_nonce' ); ?>
            <?php /** <a href="/dt3/wp-admin/admin.php?page=dt_extensions&tab=tools" class="button button-primary"> <?php esc_html_e( "No", 'disciple_tools' ) ?> </a> */ ?>
            <a href="<?= admin_url( 'admin.php?page=dt_utilities&tab=contact-import' ) ?>" class="button button-primary"> <?php esc_html_e( "No - Something is wrong!", 'disciple_tools' ) ?> </a>
            <input type="submit" name="submit" id="submit" style="background-color:#4CAF50; color:white" class="button" 
                   value=<?php esc_html_e( "Yes", 'disciple_tools' ) ?>>
        </form>
        
            
        <?php
    }

    /**
     * import contact data  
     * @param array $file filename
     * @param string $del delimeter
     * @param string $source 
     * @param string $assign 
     * @param string $header yes/no
     */
    public function import_csv( $file, $del = ',', $source = 'web', $assign = '', $header = "yes") {
        
        $people = [];
        //open file
        ini_set( 'auto_detect_line_endings', true );
        $file_data = fopen( $file['tmp_name'], "r" );
        
        $dataRows = array();
        while ( $row = fgetcsv( $file_data, 0, $del,'"','"' ) ) {
            $dataRows[] = $row;
        }
        
        $_opt_fields = Disciple_Tools_Contacts::getAllDefaultValues();
        
        $conHeadersInfo = Disciple_Tools_Contacts::get_contact_header_info(); //echo '<pre>'; var_dump($conHeadersInfo); 
        $conHeadersInfoKeys = array_keys($conHeadersInfo);
        
        if ( $header == "yes" && isset($dataRows[0])) {
            $csvHeaders = $dataRows[0];
            unset($dataRows[0]);
            
        } else {
            //if csv headers are not provided
            $csvHeaders = $conHeadersInfoKeys;
        }
        
        $tempContactsData = $dataRows;
        
        
/******************************************************************************/
        //correct csv headers
        foreach($csvHeaders as $ci=>$ch){
            $dest = $ch;
            $_mc = Disciple_Tools_Contacts::get_mapper($ch);
            if($_mc != null && strlen($_mc)>0){ $csvHeaders[$ci] = $_mc; }
        }     
        
        //loop over array
        foreach ($dataRows as $ri=>$row) {
            
            $fields = []; $mulSep = ';';
            
            foreach ($row as $index => $i) {
          
                if ($assign != '') { $fields["assigned_to"] = (int) $assign; }
                
                $fields["sources"] = [ "values" => array( [ "value" => $source ] ) ];
                
                //cleanup
                $i = str_replace( "\"", "", $i );
               
                if(isset($csvHeaders[$index])){
                    $ch = $csvHeaders[$index];
                    $pos = strpos($i, $mulSep);

                    if($ch=='title'){
                        $fields['title'] = $i;
                        
                    } else if($ch=='cf_gender'){

                        $i = strtolower( $i );
                        $i = substr( $i, 0, 1 );
                        $gender = "not-set";
                        if ($i == "m" ){ $gender = "male";
                        } else if ($i == "f" ){ $gender = "female"; }
                        $fields['cf_gender'] = $gender;
                    
                    } else if($ch=='cf_notes'){     
                    //} else if($ch=='cf_notes'||$ch=='cf_dob'||$ch=='cf_join_date'){  
                        $fields[$ch][] = $i;

                    } else {
                        
                        if ($pos === false) {
                            $fields[$ch][] = [ "value" => $i ];
                        } else {
                            $multivalued = explode($mulSep, $i);
                            foreach($multivalued as $mx){
                               $fields[$ch][] = [ "value" => $mx ]; 
                            }
                        }

                    }
                }
            }
            
            //add person
            $people[] = array( $fields );
            unset( $fields );

        }
/******************************************************************************/
       
        
        //close the file
        fclose( $file_data );
        
        $unique = array();
        foreach($csvHeaders as $ci=>$ch){
            foreach ($dataRows as $ri=>$row) {
                $unique[$ci][] = $row[$ci];
            }
        }
        
        foreach($unique as $ci=>$list){            
            $unique[$ci] = array_unique($list); 
            asort($unique[$ci]);
        }
        
        
        ?>
        
        <?php /** <pre><?php print_r($conHeadersInfo); ?></pre> */ ?>

        <h1><?php echo esc_html_e( "Mapper", 'disciple_tools' ); ?></h1>

        
        <p><strong>Important!</strong> Unmapped Columns Data will be skipped</p>
        
        <?php /** <p>
            <strong>Map import values to DT values</strong><br/>
            <small>
            [selected DT fieldname] only accepts specific values (as a Selection). 
            Please map following unique values from your data to existing values in DT. 
            You can add new values into the DT system if you want by first ...
            </small>
        </p>  */ ?>

        <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field( 'csv_mappings', 'csv_mappings_nonce' ); ?>
            
        <div class="mapper-table-container">
        <table class="mapper-table">
        <thead>
            <tr> 
                <th valign="top"> Source (Imported File) </th> 
                <th valign="top"> Destination (DT) </th> 
                <th valign="top"> Unique Values/Mapper </th>
            </tr>
        </thead>
        
        <tbody>        
        <?php

        //correct csv headers
        foreach($csvHeaders as $ci=>$ch):
            
            $colDataType = isset($_opt_fields['fields'][$ch]['type'])?$_opt_fields['fields'][$ch]['type']:null;
                
                $mapperTitle = '';
                if(isset($conHeadersInfo[$ch]['name'])){
                    $mapperTitle = $conHeadersInfo[$ch]['name'];
                } else if($ch=='title'){
                    //$mapperTitle = 'Contact Name';
                    $mapperTitle = ucwords($ch);
                } else {
                    //$mapperTitle = "<span style=\"color:red\" title=\"un-mapped data column\">{$ch}</span>";
                    $mapperTitle = "<span class=\"unmapped\" title=\"un-mapped data column\">{$ch}</span>";
                }
        
            ?>
            <tr class="mapper-coloumn" data-row-id="<?php echo $ci ?>">

                <th data-field="<?php echo $ch ?>" class="src-column">
                <?= $mapperTitle ?>
                </th>
                
                
                <td class="dest-column">
                    <?= Disciple_Tools_Contacts::getDropdownListHtml($ch,"csv_mapper_{$ci}",$conHeadersInfo,$ch,[
                        'name'=>"csv_mapper[{$ci}]", 
                        'class'=>'cf-mapper', 
                        //'onchange'=>"check_column_mappings({$ci})"
                        'onchange'=>"getDefaultValues({$ci})"
                        ],true) ?>
                    <div id="helper-fields-<?= $ci ?>" class="helper-fields"<?php if($colDataType!='key_select'): ?> style="display:none"<?php endif; ?>></div>
                </td>

                
                <td class="mapper-column">
                <div id="unique-values-<?= $ci ?>" 
                    class="unique-values"
                    data-id="<?= $ci ?>"
                    data-type="<?= $colDataType ?>"
                    <?php if($colDataType!='key_select'): ?> style="display:none"<?php endif; ?>>
                    
                    <div class="mapper-helper-text">
                        <span class="mapper-helper-title">Map import values to DT values</span><br/>
                        <span class="mapper-helper-description">
                            <span class="selected-mapper-column-name"><?= $mapperTitle ?></span> 
                            only accepts specific values (as a Selection). 
                            Please map following unique values from your data to existing values in DT. 
                            You can add new values into the DT system if you want by first ...</span>
                    </div>

                   <?php if(isset($unique[$ci])): ?> 
                   <table>
                       
                   <?php foreach($unique[$ci] as $vi=>$v): ?>
                       
                       <tr>
                           <td>
                               <?php if(strlen(trim($v))>0): ?>
                                    <?= $v ?>
                                <?php else: ?>
                                    <span class="empty">Empty</span>
                                <?php endif; ?>
                           </td>
                           
                           
                           <td>  
                               
                               <input name="VMD[<?= $ci ?>][<?= $vi ?>]" type="hidden" value="<?= $v ?>" />
                               
                               <select id="value-mapper-<?= $ci ?>-<?= $vi ?>" 
                                       name="VM[<?= $ci ?>][<?= $vi ?>]" 
                                       class="value-mapper-<?= $ci ?>" 
                                       data-value="<?= $v ?>">
                               <option>--Not Selected--</option>
                               <?php /**/ ?>
                               <?php if(isset($_opt_fields['fields'][$ch]['default'])): ?>                               
                               <?php foreach($_opt_fields['fields'][$ch]['default'] as $di=>$dt): ?>
                                   <option value="<?= $di ?>"<?php if($di==$v): ?> selected="selected"<?php endif; ?>><?= $dt['label'] ?></option>
                               <?php endforeach; ?>
                               <?php endif; ?> 
                               <?php /***/ ?>                                   
                               </select>    
                               
                               


                           </td>
                       </tr>
                       
                   <?php endforeach; ?> 
                       
                   </table>
                    
                   <?php endif; ?> 
                </div>
                </td>

                
            </tr>                
            <?php  endforeach; ?>
        </tbody>    
            
        <tfoot>
            <tr><td></td>
                <td colspan="2">


                    <input type="hidden" name="csv_data" value="<?php echo esc_html( base64_encode( serialize( $tempContactsData ) ) ); ?>">
                    <input type="hidden" name="csv_headers" value="<?php echo esc_html( base64_encode( serialize( $csvHeaders ) ) ); ?>">
                    <input type="hidden" name="csv_del_temp" value='<?= $del ?>' />
                    <input type="hidden" name="csv_source_temp" value='<?= $source ?>' />
                    <input type="hidden" name="csv_assign_temp" value='<?= $assign ?>' />

                    <?php wp_nonce_field( 'csv_mapping', 'csv_mapping_nonce' ); ?>
                    
                    <input type="submit" name="submit" id="submit" 
                           style="background-color:#4CAF50; color:white" 
                           class="button" 
                           value=<?php esc_html_e( "Next", 'disciple_tools' ) ?>>                            

                </td>
            </tr>
        </tfoot>    
        </table>
        </div>    
            
            
        <?php //$_opt_fields = Disciple_Tools_Contacts::getAllDefaultValues(); ?>            

        <div class="helper-fields-txt" style="display:none">
        <?php foreach($_opt_fields['fields'] as $_fi=>$_flds): ?>
        <div id="helper-fields-<?= $_fi ?>-txt" data-type="<?= $_flds['type'] ?>">

            <span>Field: <strong><?= $_fi ?></strong></span><br/>
            <span>Type: <strong><?= $_flds['type'] ?></strong></span><br/>
            <span>Description: <strong><?= $_flds['description'] ?></strong></span><br/>

            <?php if($_flds['type']=='key_select'||$_flds['type']=='multi_select'): ?>

            <span>Value:</span><br/>
            <ul class="default-value-options">
            <?php asort($_flds['default']); ?>    
            <?php foreach($_flds['default'] as $di=>$dt): ?>
            <li>
                <strong><span class="hlp-value"><?= $di ?></span></strong>:
                <span class="hlp-label"><?= $dt['label'] ?></span>
            </li>
            <?php endforeach; ?>
            </ul>

            <?php else: ?>
            <span>Value: <strong><?= $_flds['default'] ?></strong></span><br/>   
            <?php endif; ?>    

        </div>
        <?php endforeach; ?>   
        </div>
            
        </form>
        
        

        
        
        <script type="text/javascript">
            
            jQuery(document).ready(function(){
                getAllDefaultValues();
            });
            
            function check_column_mappings(id){
                //console.log('check_column_mappings');
                var elements, selected, selectedValue, c; 
                
                selected = document.getElementById('csv_mapper_'+id);
                selectedValue = selected.options[selected.selectedIndex].value;
                
                //console.log('selected_value='+selectedValue);
                elements = document.getElementsByClassName('cf-mapper');
                for(var i=0; i<elements.length; i++){
                    if(i!=id && selectedValue==elements[i].value){
                        //console.log('IND:' + i + ' ID:' + elements[i].id + ' VALUE:' + elements[i].value);
                        selected.selectedIndex = 'IGNORE';
                        if(elements[i].value!='IGNORE'){
                            alert('Already Mapped!');
                        }                       
                    }
                }
            }            
            
            function getAllDefaultValues(){                
                jQuery('.mapper-table tbody > tr.mapper-coloumn').each(function(){
                    //console.log('C:'+jQuery(this).attr('data-row-id'));
                    var i = jQuery(this).attr('data-row-id');
                    if(typeof i !== 'undefined'){ getDefaultValues(i); }
                });                
            }
            
            function getDefaultValues(id){                

                var selected, selectedValue, dom, ty, hlp;
                selected = document.getElementById('csv_mapper_'+id);
                selectedValue = selected.options[selected.selectedIndex].value;
                
                jQuery('.helper-fields').hide().html(''); 
                //jQuery

                //console.log('id:' + id + ' v:'+ selectedValue);
                
                //hlp = document.getElementById('helper-fields-'+selectedValue+'-txt').innerHTML;
                //document.getElementById('helper-fields-'+id).innerHTML = hlp;
                
                dom = jQuery('#helper-fields-'+selectedValue+'-txt');                
                ty = dom.attr('data-type');
                
                if(ty == 'key_select'){
                    hlp = dom.html(); //console.log('hlp:' + hlp);
                    
                    jQuery('#unique-values-'+id).show();
                    jQuery('#unique-values-'+id).find('.selected-mapper-column-name').html( jQuery('#csv_mapper_'+id).val() );
                    jQuery('#helper-fields-'+id).html( hlp ).show();
                    
                    jQuery('.value-mapper-'+id).html('');
                    
                    jQuery('.value-mapper-'+id).append('<option>--select--</option>');
                    
                    //h_sel = jQuery('.value-mapper-'+id).attr('data-value');
                    
                    //default-value-options
                    jQuery.each( dom.find('.default-value-options li'), function(i,v){
                        var h_this, h_value, h_label, h_html, h_sel;
                        h_this = jQuery(this);
                        h_value = h_this.find('.hlp-value').html();
                        h_label = h_this.find('.hlp-label').html();
                        if(!h_label.length>0){ h_label = h_value.toUpperCase(); }
                        //console.log('id:' +i+' value:'+h_value+' label:'+h_label);                        
                        
                        
                        h_html = '<option value="'+h_value+'"'; 
                        //if(h_sel==h_value){ h_html = h_html + ' selected="selected"'; }
                        h_html = h_html + '>'+h_label+'</option>';
                        
                        jQuery('.value-mapper-'+id).append(h_html);
                    });
                    
                    jQuery('.value-mapper-'+id).each(function(){
                        h_sel = jQuery(this).attr('data-value');
                        
                        jQuery(this).find('option').each(function(){
                            if(h_sel==jQuery(this).attr('value')){
                                jQuery(this).attr('selected','selected');
                            }
                        });
                        
                    });
                    
                } else {
                    jQuery('#unique-values-'+id).hide();
                }
            }
            
        </script>    

        <?php /** ?>
        <h3><?php echo esc_html_e( "Is This Data In The Correct Fields?", 'disciple_tools' ); ?></h3>
        <?php /** */ ?>
   
<?php /*************************************************************************        
        <table class="data-table">
        <thead>
        <tr>
        
        <th><?php esc_html_e( "Title", 'disciple_tools' ) ?></th>    
            
        <?php foreach($csvHeaders as $ci=>$ch): ?>        
        <?php if($ch=='title'){ continue; } ?>
        
        <th>            
            <?php if(isset($conHeadersInfo[$ch]['name'])): ?> 
                <span class="cflabel"> <?= $conHeadersInfo[$ch]['name'] ?> </span><br/> 
            <?php endif; ?>            
            <span class="cffield"> <?php esc_html_e( $ch, 'disciple_tools' ) ?> </span>
        </th>
        <?php endforeach; ?>

        <th><span class="cflabel"><?php esc_html_e( "Source", 'disciple_tools' ) ?></span></th>
        <th><span class="cflabel"><?php esc_html_e( "Assigned To", 'disciple_tools' ) ?></span></th>
        </tr>
        </thead>               
        
        <tbody>
        <?php foreach($people as $pid=>$pplData): ?> 
        <?php $personData = $pplData[0]; ?>
        
        <tr id="person-data-item-<?php echo $pid ?>" 
                 class="person-data-item">            
        <td>
            <?php echo esc_html( $personData['title'] ) ?>
        </td>       
        
        <?php foreach($csvHeaders as $ci=>$ch): ?>  
        
            <?php if($ch=='title'){ continue; } ?>
            <td data-key="<?= $ch ?>">
            <?php if($ch=='cf_gender'): ?>
                <?php echo isset( $personData[$ch] ) ? esc_html( $personData[$ch] ) : "None" ?>
            <?php elseif($ch=='cf_notes'||$ch=='cf_dob'||$ch=='cf_join_date'): ?>
                <?php echo isset( $personData[$ch][0] ) ? esc_html( $personData[$ch][0] ) : "None" ?>
            <?php else: ?>
                <?php echo isset( $personData[$ch][0]["value"] ) ? esc_html( $personData[$ch][0]["value"] ) : "None" ?>
            <?php endif; ?>        
            </td>    
        <?php endforeach; ?>
       
        
        <td>
            <?php echo esc_html( $personData['sources']["values"][0]["value"] ) ?>
        </td>
        
        <td>
            <?php echo ( isset( $personData['assigned_to'] ) && $personData['assigned_to'] != '' ) ? esc_html( get_user_by( 'id', $personData['assigned_to'] )->data->display_name ) : "Not Set" ?>
        </td>
        
        </tr>
        
        <?php endforeach; ?>
        </tbody>
        
        </table>
****************************************************************************/ ?>
<?php /**         
<h3><?php echo esc_html_e( "Data (for DEBUG Purpose Only)", 'disciple_tools' ); ?></h3>
<?php 
$html = Disciple_Tools_Contacts::display_data($people, $conHeadersInfo, $csvHeaders); 
echo $html;
?> */ ?>
        
        <?php /** @jerome - start-of "hide the data display in step2" --> 
        <form method="post" enctype="multipart/form-data">
            <?php // export form ?>
            <input type="hidden" name="csv_contacts" value="<?php echo esc_html( base64_encode( serialize( $people ) ) ); ?>">
            <?php wp_nonce_field( 'csv_correct', 'csv_correct_nonce' ); ?>
            <?php // <a href="/dt3/wp-admin/admin.php?page=dt_extensions&tab=tools" class="button button-primary"> <?php esc_html_e( "No", 'disciple_tools' ) ?> </a> ?>
            <a href="<?= admin_url( 'admin.php?page=dt_utilities&tab=contact-import' ) ?>" class="button button-primary"> <?php esc_html_e( "No - Something is wrong!", 'disciple_tools' ) ?> </a>
            <input type="submit" name="submit" id="submit" style="background-color:#4CAF50; color:white" class="button" 
                   value=<?php esc_html_e( "Yes", 'disciple_tools' ) ?>>
        </form>
        @jerome - end-of "hide the data display in step2" -->. 
        */ ?>
        <?php
    }

    private function insert_contacts( $contacts) {
        
        /** ?><pre><?php var_dump($contacts) ?></pre><br/><pre><?php print_r($contacts) ?></pre><?php         
        die('ENGINE-DIED! ERROR'.__LINE__); */
        
        set_time_limit( 0 );
        global $wpdb;
        ?>
        <div id="import-logs">&nbsp;</div>
        <div id="contact-links">&nbsp;</div>
        <script type="text/javascript">
            var pid = 1000;
            function process( q, num, fn, done ) {
                // remove a batch of items from the queue
                var items = q.splice(0, num),
                    count = items.length;

                // no more items?
                if ( !count ) {
                    // exec done callback if specified
                    done && done();
                    // quit
                    return;
                }

                // loop over each item
                for ( var i = 0; i < count; i++ ) {
                    // call callback, passing item and
                    // a "done" callback
                    fn(items[i], function() {
                        // when done, decrement counter and
                        // if counter is 0, process next batch
                        --count || process(q, num, fn, done);
                        pid++;
                    });                    
                    
                }
            }

            // a per-item action
            function doEach( item, done ) {                
                console.log('starting ...' ); //t('starting ...');
                jQuery.ajax({
                    type: "POST",
                    data: item,
                    contentType: "application/json; charset=utf-8",
                    dataType: "json",
                    url: "<?php echo esc_url_raw( rest_url() ); ?>" + `dt/v1/contact/create?silent=true`,
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', "<?php /*@codingStandardsIgnoreLine*/ echo sanitize_text_field( wp_unslash( wp_create_nonce( 'wp_rest' ) ) ); ?>");
                    },
                    success: function(data) {
                        console.log('done'); t('PID#'+pid+' done');
                        //jQuery('#contact-links').append('<li><a href="'+data.permalink+'" target="_blank">Contact #'+data.post_id+'</a></li>');
                        done();
                    },
                    error: function(xhr) { // if error occured
                        alert("Error occured.please try again");
                        console.log("%o",xhr);
                        t('PID#'+pid+' Error occured.please try again');
                    }
                });
            }

            // an all-done action
            function doDone() {
                console.log('all done!'); t('all done');
                jQuery("#back").show();
            }
            
            function t(m){
                var el, v;
                el = document.getElementById("import-logs");
                v = el.innerHTML;
                v = v + '<br/>' + m;
                el.innerHTML = v;                
            }
            
            function reset(){
                document.getElementById("import-logs").value = '';
            }
        </script>
        <?php
        global $wpdb;
        $js_contacts = [];
        foreach ( $contacts as $num => $f ) {
            $js_array = wp_json_encode( $f[0] );
            $js_contacts[] = $js_array;
            $wpdb->queries = [];
        }
        ?>
        <script type="text/javascript">
            
            reset();
            t('started processing queue!');
            
            // start processing queue!
            queue = <?php echo wp_json_encode( $js_contacts ); ?>;
            process(queue, 5, doEach, doDone);
        </script>
        <?php
        $num = count( $contacts );
        echo esc_html( sprintf( __( "Creating %s Contacts DO NOT LEAVE THE PAGE UNTIL THE BACK BUTTON APPEARS", 'disciple_tools' ), $num ) );
        ?>
        <form id="back" method="post" enctype="multipart/form-data" hidden>
            <a href="" class="button button-primary"> <?php esc_html_e( "Back", 'disciple_tools' ) ?> </a>
        </form>
        
        <?php
        exit;
    }
    

}
Disciple_Tools_Contact_Import_Tab::instance();
