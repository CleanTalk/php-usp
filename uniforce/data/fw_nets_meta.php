<?php
$fw_nets_meta = array (
  'line_length' => 27,
  'cols' => 
  array (
    'net' => 
    array (
      'type' => 'int',
      'length' => 11,
    ),
    'mask' => 
    array (
      'type' => 'int',
      'length' => 11,
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
    'net' => 
    array (
      'status' => 'ready',
      'type' => 'b_tree',
    ),
  ),
  'cols_num' => 4,
  'rows' => 0,
  'median' => 5000000000,
);
