<?php


namespace Cleantalk\USP\File;


use Cleantalk\USP\Common\Err;

class Storage {
    
    private $folder;
    private $name;
    
    private $path;
    private $path_temp;
    
    /**
     * @var false|resource
     */
    private $stream;
    private $stream_temp;
    private $cols;
    private $line_length;
    
    private $input_buffer;
    
    private $buffer;
    private $buffer_size;
    
    private $row_separator = "\n";
    private $row_placeholder = "\x00";
    
    private $output;
    
    public function __construct( $name, $cols, $folder = null ){
    
        $this->folder      = $folder ?: CT_USP_ROOT . 'data' . DIRECTORY_SEPARATOR;
        $this->name        = $name;
        $this->path        = $this->folder . $name . '.storage';
        $this->path_temp        = $this->folder . $name . '_temp.storage';
        $this->cols        = $cols;
        $this->line_length = array_sum( array_column( $this->cols, 'length' ) );
        
        $this->stream = fopen( $this->path, 'a+b' );
        $this->stream_temp = fopen( $this->path_temp, 'a+b' );
    }
    
    /**
     * @param $row
     *
     * @return bool|int
     */
    public function put( $row ) {
        
        $res = false;
        
        if(
            $this->checkRowFormat( $row ) &&
            $this->covertRowToRaw( $row )
        ){
            fseek( $this->stream, 0, SEEK_END );
            $res = fwrite( $this->stream, $this->input_buffer . $this->row_separator );
        }
        
        if ( ! $res ){
            $err = error_get_last();
            Err::add( $err['message'] );
        }
            
        return (bool) $res;
    }

    /**
     * @param $row
     *
     * @return bool|int
     */
    public function putTemp( $row ) {
        
        $res = false;
        
        if(
            $this->checkRowFormat( $row ) &&
            $this->covertRowToRaw( $row )
        ){
            fseek( $this->stream, 0, SEEK_END );
            $res = fwrite( $this->stream_temp, $this->input_buffer . $this->row_separator );
        }
        
        if ( ! $res ){
            $err = error_get_last();
            Err::add( $err['message'] );
        }
            
        return (bool) $res;
    }
    
    /**
     * @return bool
     */
    public function delete(){
        return ftruncate( $this->stream, 0 ) && unlink( $this->path );
    }

    /**
     * @return bool
     */
    public function deleteTemp(){
        return ftruncate( $this->stream_temp, 0 ) && unlink( $this->path_temp );
    }
    
    private function checkRowFormat( $data ){
        
        if( count( $data ) !== count( $this->cols ) ){
            Err::add( 'Cols number does not match. Given ' . count( $data ) . ', needed: ' . count( $this->cols ) );
            
            return false;
        }
        
        
        return true;
    }
    
    private function covertRowToRaw( &$row ){
    
        $this->input_buffer = '';
        
        foreach( $row as $name => $col ){
        
            // Converting data to the column type
            switch( $this->cols[ $name ]['type'] ){
                case 'int':
                    $val = (int) $col;
                    break;
                case 'string':
                    $val = (string) $col;
                    break;
                default:
                    $val = $col;
            }
        
            // Converting to raw format
            // Padding the string with placeholders to the val length
            $this->input_buffer .= str_pad(
                substr( $val, 0, $this->cols[ $name ]['length'] ),
                $this->cols[ $name ]['length'],
                $this->row_placeholder,
                STR_PAD_LEFT
            );
        }
        
        return (bool) $this->input_buffer;
    }
    
    public function get( $addresses ){
    
        return $this->getRawDataToBuffer( $addresses ) && $this->getDataFromBufferToOutput()
            ? $this->output
            : false;
    }
    
    private function getRawDataToBuffer( $addresses ){
        
        foreach( $addresses as $address ){
            
            if( ! $address ){
                continue;
            }
            
            $byte_offset = ( $address - 1 ) * $this->line_length + $address - 1;
            $byte_amount = $this->line_length;
            
            // Set needed position
            if( fseek( $this->stream, $byte_offset, SEEK_SET ) === - 1 ){
                Err::add( 'Can not find file position: ' . error_get_last()['message'] );
                
                return false;
            }
            
            // Get data
            $this->buffer .= fread( $this->stream, $byte_amount );
            
            if( ! $this->buffer ){
                Err::add( 'Can not read data: ' . error_get_last()['message'] );
                
                return false;
            }
        }
    
        $this->buffer_size = strlen( $this->buffer );
        
        return true;
    }
    
    private function getDataFromBufferToOutput(){
    
        if( $this->buffer_size % $this->line_length !== 0 ){
            Err::add( 'Buffer size is not correct');
            
            return false;
        }
        
        for( $buffer_read_offset = 0,
             $read_line_offset = 0;
            
            $buffer_read_offset + 1 < $this->buffer_size;
    
            $buffer_read_offset += $this->line_length,
            $line             = array(),
            $read_line_offset = 0
        ){
        
            // Extract row and reduce buffer by row length
            $buffer_row = substr( $this->buffer, $buffer_read_offset, $this->line_length );
            
            foreach( $this->cols as $name => $col ){
            
                $line[] = str_replace(
                    $this->row_placeholder,
                    '',
                    substr( $buffer_row, $read_line_offset, $col['length'] )
                );
            
                $read_line_offset += $col['length'];
            }
        
            $this->output[] = array_combine( array_keys( $this->cols ), $line );
        
        }
        
        return true;
        
    }
}