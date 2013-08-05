<?php

// Include the Colander class:
App::uses('Colander','Lib/Colander');

class ExampleController {

    public function example_page(){
        $this->loadModel('ExampleModel');

        // Instantiate a colander object
        $colander = new Colander($this);
        // Define what fields the colander filters by. See the colander class for more info
        //     Allowed 'conds' and 'mode' can be specified per field.
        $colander->filter_by(array(
            'ExampleModel'=>array(
                'col1'=>array('name'=>'Column 1','cond'=>array('='),'mode'=>'strict'),
                'col2'=>array('name'=>'Column 2'),
                'col3',
                'col4',
            ),
        ));
        // Set $items in the view to be the filtered results from the colander
        $this->set('items',$colander->items(
            // The first argument to items() is the root model to ->find() from
            $this->ExampleModel,
            // The second argument is an array similar to ARRAY like in model->find('something', ARRAY)
            array(
                'joins' => array(
                    array(
                        'table' => 'TABLENAME',
                        'alias' => 'Model2',
                        'type' => 'INNER',
                        'conditions' => 'Model2.id = ExampleModel.model2_id',
                    ),
                ),
            )
        ));
        // Set $form in the view to be the generated colander form
        $this->set('form',$colander->form());
    }
}
?>
