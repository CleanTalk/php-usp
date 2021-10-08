<?php
$test_meta = array (
  'cols' => 
  array (
    'network' => 
    array (
      'type' => 'int',
      'length' => 3,
    ),
    'mask' => 
    array (
      'type' => 'int',
      'length' => 3,
    ),
    'status' => 
    array (
      'type' => 'int',
      'length' => 2,
    ),
    'is_personal' => 
    array (
      'type' => 'int',
      'length' => 2,
    ),
  ),
  'description' => 'Test',
  'indexes' => 
  array (
    0 => 
    array (
      'columns' => 
      array (
        0 => 'network',
      ),
      'status' => 'ready',
      'type' => 'btree',
    ),
  ),
  'rows' => 10,
  'line_length' => 10,
  'cols_num' => 4,
);
