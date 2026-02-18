<?php
/**
 * Execute a Python command from the main.py script
 * 
 * @param string $command The command to execute
 * @param array $params Additional parameters
 * @return array The output and return code from the command
 */
function executePythonCommand($command, $params = []) {
    if (!defined('PYTHON_SCRIPT_PATH') || PYTHON_SCRIPT_PATH === '') {
        return [
            'output' => ['PYTHON_SCRIPT_PATH is not configured.'],
            'return_code' => 1
        ];
    }

    // Prepare the command string with parameters
    $param_string = '';
    foreach ($params as $key => $value) {
        $param_string .= " --$key " . escapeshellarg($value);
    }
    
    $python_cmd = "python " . PYTHON_SCRIPT_PATH . " $command$param_string";
    
    // Execute the command
    $output = [];
    $return_var = 0;
    exec($python_cmd, $output, $return_var);
    
    return [
        'output' => $output,
        'return_code' => $return_var
    ];
}
?>
