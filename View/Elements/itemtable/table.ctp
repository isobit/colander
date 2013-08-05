<?php
// NOTE: This element requires PHP 5.3+ since it makes use of lambda-style functions
// $items must be set before this element can be called. $items should be the result of a Model->find()
// $cols must be set before this element can be called. $cols must be of format:
//      $cols = array(
//          array('NAME','MODEL','COLUMN'),
//          array('NAME',function($item){FUNCTION;}),
//      );


if (!empty($items)){
    echo '<table>';
    echo '<thead>';
    foreach ($cols as $col){
        echo '<th>'.$col[0].'</th>';
    }
    echo '</thead>';
    echo '<tbody>';
    foreach ($items as $item){
        echo '<tr>';
        foreach ($cols as $col){
            if (is_callable($col[1])){
                $res = call_user_func($col[1], $item);
            } else {
                $res = $item[$col[1]][$col[2]];
            }
            if ($res != ''){
                echo '<td>'.$res.'</td>';
            } else {
                echo '<td>-</td>';
            }
        }
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
} else {
    echo '<h2>No results</h2>';
}
?>
