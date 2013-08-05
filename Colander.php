<?php
//--------------------------------------------------
// Colander Class                                   |
//--------------------------------------------------
//Author: Josh Glendenning
//--------------------------------------------------

class Colander {

    //--------------------------------------------------
    // Constructor                                    |
    //--------------------------------------------------
    // To instantiate, use $colander = new Colander($this)
    // The $condmode argument tells the filter what to do with
    //  fields that have a disabled cond. Current options:
    //      -'strict': Use '=' cond by default.
    //      -'loose': Use 'LIKE' cond by default, and surround
    //          value with '%'
    public function __construct($self,$condmode='loose'){
        $this->self = $self;
        $this->pagedata = $self->request->query;
        $this->condmode = $condmode;
    }

    //--------------------------------------------------
    // Helper Functions                               |
    //--------------------------------------------------

    private function needs(&$thing){
        if (!isset($thing)){
            $this->self->pageNotFound();
        }
    }

    //--------------------------------------------------
    // Filtered Fields array                          |
    //--------------------------------------------------
    // Format:
    // array(
    //      'MODEL'=>array(
    //          'COLUMN'=>array(
    //              'cond'=>array('=','>','<',...),
    //              'name'=>'NAME',
    //          )));
    // 'cond' value can be: 
    //      -True, to accept all default conds
    //      -False, to disable conds
    //      -Array of cond chars, to specify cond whitelist

    var $filtered_fields = array();
    public function filter_by($ff){
        $this->filtered_fields = $ff;
    }

    //--------------------------------------------------
    // Field Array                                    |
    //--------------------------------------------------
    // Arrays should be of format:
    //        array(
    //            'MODEL'=>array(
    //                'COLUMN'=>array(
    //                    'cond'=>'[=,>,<,etc]',
    //                    'val'=>'VAL'
    //                )
    //            )
    //        )
    // This translates to GET/POST data of format:
    //      Field-MODEL-COLUMN-cond=[=,>,<,etc]
    //      Field-MODEL-COLUMN-val=VAL
    //--------------------------------------------------
    public function get_fields(){
        $data = $this->pagedata;

        $fields = array();

        foreach ($data as $key=>$val){
            // Ignore if there's less that three things (should be Field.MODEL.COLUMN.PART)
            if (substr_count($key,'-') != 3){continue;}
            $entry = explode('-',$key);
            if ($entry[0] != 'Field'){continue;}
            if ($entry[3] != ('val' || 'cond')){continue;}
            if (empty($val) && $val != '0'){continue;}
             //Write value to fields[MODEL][COLUMN][PART]
            $fields[$entry[1]][$entry[2]][$entry[3]] = $val;
        }

        $this->fields = $fields;
        return $fields;
    }

    public function get_order(){
        $data = $this->pagedata;

        if (!isset($data['sortby'])){
            return False;
        }

        $order = $data['sort-by'];
        $order .= $data['sort-ord'] ? ' '.$data['sort-ord']: '';

        $this->order = $order;
        return $order;
    }


    public function items($rootmodel,$findextra=False){
        $conditions = array();
        $fields = $this->get_fields();
        $ff = $this->filtered_fields;
        foreach ($fields as $model=>$cols){
            foreach ($cols as $col=>$parts){
                if (!isset($parts['val'])){continue;}

                $condmode = $this->condmode;

                if (isset($ff[$model][$col]['mode'])){
                    $condmode = $ff[$model][$col]['mode'];
                }

                if ($condmode == 'strict'){
                    $cond = $parts['cond'] ?: '=';
                    $conditions[$model.'.'.$col.' '.$cond] = $parts['val'];
                }
                elseif ($condmode == 'loose'){
                    if (isset($parts['cond'])){
                        $cond = $parts['cond'];
                        $val = $parts['val'];
                    } else {
                        $cond = 'LIKE';
                        $val = '%'.$parts['val'].'%';
                    }
                    $conditions[$model.'.'.$col.' '.$cond] = $val;
                } else {
                    throw new InvalidArgumentException("Colander's condmode argument only accepts strings 'loose' or 'strict'.");
                }
            }
        }

        if(empty($conditions)){return array();}

        $order = $this->get_order();

        $findarray = array('conditions'=>$conditions);
        if ($order){
            $findarray['order'] = $order;
        }
        if (is_array($findextra)){
            foreach ($findextra as $key=>$val){
                $findarray[$key] = $val;
            }
        }
        $items = $rootmodel->find('all',$findarray);

        return $items;
    }

    //--------------------------------------------------
    // Form Functions                                  |
    //--------------------------------------------------

    //--- Helpers ---------------------------------
    private function tag($s,$tag,$options=array()){
        $opts = '';
        if (!empty($options)){
            $opts .= ' ';
            foreach ($options as $key=>$val){
                $opts .= $key.'="'.$val.'" ';
            }
        }
        return '<'.$tag.$opts.'>'.$s.'</'.$tag.'>';
    }
    private function tr($s,$options=array()){
        return $this->tag($s,'tr',$options);
    }
    private function td($s,$options=array()){return $this->tag($s,'td',$options);}

    private function get_input_val($model,$column,$fields){
        $id = 'Field-'.$model.'-'.$column.'-'.'val';
        $val = isset($fields[$model][$column]['val']) ? $fields[$model][$column]['val'] : '';
        $res = '<input type="text" style="width:100%;"'
            .'name="'.$id.'" '
            .'id="'.$id.'" '
            .'value="'.$val.'">'
            .'</input>';
        return $res;
    }
    private function get_input_cond($model,$column,$fconds,$fields){
        if ($fconds == False){
            return '';
        }
        elseif (is_array($fconds)){
            $conds = $fconds;
        }
        elseif ($fconds === 'int'){
            $conds = array('=','<','>','<=','>=','NOT');
        }
        elseif ($fconds === 'intstrict'){
            $conds = array('=','<','>','<=','>=');
        }
        elseif ($fconds === True){
            $conds = array('=','<','>','<=','>=','NOT','LIKE');
        }

        $id = 'Field-'.$model.'-'.$column.'-'.'cond';
        $val = isset($fields[$model][$column]['cond']) ? $fields[$model][$column]['cond'] : '=';

        $res = '<select style="width:40px;" name="'.$id.'">';
        foreach ($conds as $cond){
            $selected = ($val == $cond) ? 'selected="selected"' : '';
            $res .= '<option value="'.$cond.'" '.$selected.'>'.$cond.'</option>';
        }
        $res .= '</select>';

        return $res;
    }
    // Form Function ---------------------
    // Returns html for the filter form.
    public function form(){

        $fields = $this->fields;

        $ff = $this->filtered_fields;

        $res = '<div id="filter_form"><form method="GET"><table class="filter_table">';
        $res .= '<thead><th>Field</th><th>Op</th><th>Search</th></thead><tbody>';
        foreach ($ff as $model=>$cols){
            foreach ($cols as $col=>$val){
                $conds = (is_array($val) && isset($val['cond'])) ? $val['cond'] : True;
                $name =  (is_array($val) && isset($val['name'])) ? $val['name'] : $val;
                $column =  is_array($val) ? $col : $val;
                $res .= $this->tr(
                    $this->td($name)
                    .$this->td($this->get_input_cond($model,$column,$conds,$fields),array('style'=>'width:45px;'))
                    .$this->td($this->get_input_val($model,$column,$fields))
                );
            }
        }
        $res .= '</tbody></table>';
        $res .= '<div id="filter_buttons"><input type="button" value="All" onclick="window.location = window.location.pathname" />'
            .'<input type="submit" value="Filter" /></div>';
        $res .= '</form></div>';
        return $res;
    }

}

?>
