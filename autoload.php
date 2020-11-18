<?php

function SynchronyAutoload($className){ 
    $array_paths = array(
        'src/',
        'libs/',
        'libs/phpexcel/phpexcel/Classes/',
        'libs/phpexcel/phpexcel/Classes/PHPExcel',
        'db/'
    );
    $extension =  spl_autoload_extensions();
    foreach($array_paths as $path)
    {
        $className = str_replace('\\', DIRECTORY_SEPARATOR, $className);
        $filename = __DIR__ . DIRECTORY_SEPARATOR . $className . $extension;
        //$file = sprintf('%s%s/%s.php', AP_SITE, $path, $class_name);
        echo $filename . "\n";
        if(is_file($filename))
        {
            include_once $filename;
            break;
        }

    }
}

spl_autoload_extensions('.php');
spl_autoload_register('SynchronyAutoload');
