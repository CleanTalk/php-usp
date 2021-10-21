<?php
$fw_nets_meta = array (
  'line_length' => 26,
  'cols' => 
  array (
    'network' => 
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
    0 => 
    array (
      'columns' => 
      array (
        0 => 'network',
      ),
      'status' => false,
      'type' => 'btree',
    ),
  ),
  'cols_num' => 4,
  'rows' => 0,
);
