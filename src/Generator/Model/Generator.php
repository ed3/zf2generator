<?php

namespace Generator\Model;

class Generator
{

    protected $dbAdapter;

    public function __construct($dbAdapter)
    {
        $this->dbAdapter = $dbAdapter;
    }

    protected function switchToRootDir()
    {
        $publicFolderPath = $_SERVER['DOCUMENT_ROOT'];
        chdir($publicFolderPath);
        chdir('..');
        return;
    }

    public function getDatabaseTables()
    {
        $sql = "Show tables";
        $stmt = $this->dbAdapter->query($sql);
        $rows = $stmt->execute();
        $tables = array();
        foreach ($rows as $row) {
            $tables[] = reset($row);
        }
        return $tables;
    }

    public function getModules()
    {
        $modules = array_diff(scandir('module'), array('..', '.'));
        $key = array_search('Generator', $modules);
        unset($modules[$key]);
        return $modules;
    }

    public function generateForm($module, $table)
    {
        $directories = array_diff(scandir("module/$module/src/$module"), array('..', '.'));
        if (!in_array('Form', $directories)) {
            mkdir("module/$module/src/$module/Form", 0777);
        }
        chdir("module/$module/src/$module/Form");
        $formDirPath = getcwd();
        $filename = $this->getFormName($table);
        $handler = fopen($formDirPath . '/' . $filename . '.php', 'w+');
        $this->writeCode($handler, $table, $module);
    }

    protected function getFormName($tableName)
    {
        $name = '';
        $formName = preg_replace("/[^A-Za-z0-9 ]/", '_', $tableName);
        $stringParts = explode("_", $formName);
        foreach ($stringParts as $part) {
            $partArray = str_split($part);
            $partArray[0] = strtoupper($partArray[0]);
            $name .= implode('', $partArray);
        }

        return $name;
    }

    protected function getFieldLabel($field)
    {
        $label = '';
        $formName = preg_replace("/[^A-Za-z0-9 ]/", '_', $field);
        $stringParts = explode("_", $formName);
        foreach ($stringParts as $part) {
            $partArray = str_split($part);
            $partArray[0] = strtoupper($partArray[0]);
            $partArray[] = ' ';
            $label .= implode('', $partArray);
        }

        return $label;
    }

    protected function writeCode($handler, $table, $module)
    {
        $sql = "SHOW COLUMNS FROM $table";
        $stmt = $this->dbAdapter->query($sql);
        $rows = $stmt->execute();
        $fields = array();
        foreach ($rows as $row) {
            $fields[] = $row;
        }
        //echo '<pre>'; print_r($fields); exit;

        fwrite($handler, "<?php \n\n");
        fwrite($handler, "namespace $module\Form; \n\n");
        fwrite($handler, "use Zend\Form\Form; \n");
        fwrite($handler, "use Zend\InputFilter\Factory as InputFactory; \n");
        fwrite($handler, "use Zend\InputFilter\InputFilter; \n");
        fwrite($handler, "use Zend\InputFilter\InputFilterAwareInterface; \n");
        fwrite($handler, "use Zend\InputFilter\InputFilterInterface; \n\n");
        fwrite($handler, "class {$this->getFormName($table)} extends Form implements InputFilterAwareInterface \n{ \n");
        fwrite($handler, "    protected \$inputFilter; \n\n");
        fwrite($handler, "    public function __construct(\$name = 'null') \n    {\n");
        fwrite($handler, "        parent::__construct('$table');\n ");
        fwrite($handler, "       \$this->setAttribute('method', 'post');\n ");
        fwrite($handler, "       \$this->setAttribute('id', '$table');\n \n");
        foreach ($fields as $field) {
            fwrite($handler, "        \$this->add(array(\n");
            fwrite($handler, "            'name' => '{$field['Field']}', \n");
            fwrite($handler, "            'required' => 'required', \n");
            fwrite($handler, "            'attributes' => array( \n");
            fwrite($handler, "                'type' => 'text', \n");
            fwrite($handler, "                'id' => '{$field['Field']}', \n");
            fwrite($handler, "            ), \n");
            fwrite($handler, "            'options' => array( \n");
            fwrite($handler, "                'label' => '{$this->getFieldLabel($field['Field'])}', \n");
            fwrite($handler, "            ), \n");
            fwrite($handler, "        )); \n\n");
        }
        fwrite($handler, "    }\n\n");
        fwrite($handler, "    public function getInputFilter() \n    {\n");
        fwrite($handler, "        if (!\$this->inputFilter) {\n");
        fwrite($handler, "            \$inputFilter = new InputFilter();\n");
        fwrite($handler, "            \$factory = new InputFactory();\n\n");
        foreach ($fields as $field) {
            //echo $field['Null']; exit;
            fwrite($handler, "            \$inputFilter->add(\$factory->createInput(array(\n");
            fwrite($handler, "                        'name' => '{$field['Field']}',\n");
            $required = ($field['Null'] == 'NO') ? 'true' : 'false';
            //echo $required; exit;
            fwrite($handler, "                        'required' => $required,\n");
            fwrite($handler, "                        'filters' => array(\n");
            fwrite($handler, "                            array('name' => 'StripTags'),\n");
            fwrite($handler, "                            array('name' => 'StringTrim'),\n");
            fwrite($handler, "                        ),\n");
            fwrite($handler, "                        'validators' => array(\n");
            if ($field['Null'] == 'NO') {
                fwrite($handler, "                            array(\n");
                fwrite($handler, "                                'name' => 'NotEmpty',\n");
                fwrite($handler, "                                'options' => array('message' => '{$this->getFieldLabel($field['Field'])} cannot be empty'),\n");
                fwrite($handler, "                            ),\n");
            }
            fwrite($handler, "                        ),\n");
            fwrite($handler, "             )));\n\n");
        }
        fwrite($handler, "             \$this->inputFilter = \$inputFilter;\n");
        fwrite($handler, "        }\n\n");
        fwrite($handler, "        return \$this->inputFilter;\n\n");
        fwrite($handler, "    }\n");
        fwrite($handler, "}");
    }

    public function generateModule($moduleName, $createController, $controllerName = null)
    {
        $this->createModuleDirectoryStructure($moduleName);
        $this->createModuleFile($moduleName);
        $this->createModuleConfigFile($moduleName);
        $this->addClassMapFile($moduleName);
        if ($createController && !empty($controllerName)) {
            $this->createControllerForModule($moduleName, $controllerName);
        }
        $this->addModuleInProject($moduleName);
    }

    protected function createModuleDirectoryStructure($moduleName)
    {
        chdir('module');
        mkdir($moduleName, 0777);
        chdir($moduleName);
        mkdir('config', 0777);
        mkdir('src', 0777);
        mkdir('view', 0777);
        chdir('src');
        mkdir($moduleName, 0777);
        chdir($moduleName);
        mkdir('Controller', 0777);
        chdir('..');
        chdir('..');
        chdir('view');
        mkdir(strtolower($moduleName), 0777);
        mkdir('error', 0777);
        mkdir('layout', 0777);
        chdir('../../..');
        return;
    }

    protected function createModuleFile($moduleName)
    {
        $moduleFileTemplate = $this->getModuleFileTemplate();
        $moduleFileTemplate = str_replace('ModuleName', $moduleName, $moduleFileTemplate);
        $this->switchToRootDir();
        chdir('module');
        chdir($moduleName);
        $handle = fopen('Module.php', 'w+');
        fwrite($handle, $moduleFileTemplate);
    }

    protected function getModuleFileTemplate()
    {
        $this->switchToRootDir();
        chdir('module/Generator/src/Generator/Model');
        return file_get_contents('Module.php');
    }

    protected function createModuleConfigFile($moduleName)
    {
        $array = $this->getModuleConfigFileTemplate();
        $this->switchToRootDir();
        chdir("module/$moduleName/config");
        $handle = fopen('module.config.php', 'w+');
        $content = "<?php \n return array(\n";
        $content .= $this->writeConfig($array);
        $content .= ");";
        fwrite($handle, $content);
    }

    protected function getModuleConfigFileTemplate()
    {
        $this->switchToRootDir();
        chdir('module/Generator/src/Generator/Model');
        $array = include('module.config.php');
        return $array;
    }

    protected function writeConfig($array, $content = null, $count = null)
    {
        $content = '';
        $count = $count == null ? 1 : $count;
        foreach ($array as $key => $config) {
            $intends = $this->getIntends($count);
            if ($key != 'template_path_stack' || is_int($key)) { //hack for writing php Constants :/
                if (!is_array($config)) {
                    $content .= "$intends'$key' => '$config',\n";
                } else {
                    $content .= "$intends'$key' =>  array( \n";
                    $content .= $this->writeConfig($config, $content, $count + 1);
                    $content .= "$intends),\n";
                }
            } else {
                $content .= "$intends'$key' => array( __DIR__ . '/../view',),\n";
            }
        }

        return $content;
    }

    protected function getIntends($count)
    {
        $spaces = $count * 4;
        $intends = '';
        for ($i = 1; $i <= $spaces; $i++) {
            $intends .= ' ';
        }
        return $intends;
    }

    public function createControllerForModule($moduleName, $controllerName)
    {
        $this->switchToRootDir();
        chdir("module/$moduleName/src/$moduleName/Controller");
        $controllerFullName = $controllerName . 'Controller';
        $handle = fopen("$controllerFullName.php", "w+");
        $content = "<?php\n\n";
        $content .= "namespace $moduleName\Controller; \n\n";
        $content .= "use Zend\Mvc\Controller\AbstractActionController;\n";
        $content .= "use Zend\View\Model\ViewModel;\n\n";
        $content .= "class $controllerFullName extends AbstractActionController \n";
        $content .= "{\n";
        $content .= "    public function indexAction()\n";
        $content .= "    {\n\n";
        $content .= "    }\n";
        $content .= "}";
        fwrite($handle, $content);
        $this->addRouterForController($controllerName, $moduleName);
        $this->addViewFolderForController($controllerName, $moduleName);
    }

    protected function addRouterForController($controllerName, $moduleName)
    {
        $moduleConfig = $this->getModuleConfig($moduleName);
        $controllerFullName = $controllerName . 'Controller';
        $moduleConfig['controllers']['invokables']["$moduleName\Controller\\$controllerName"] = "$moduleName\Controller\\$controllerFullName";
        $controllerRouter = array(
            'type' => 'segment',
            'options' => array(
                'route' => '/'.strtolower($moduleName) . '/' . strtolower($controllerName) . '[/:action][/:id]',
                'constraints' => array(
                    'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    'id' => '[0-9]+',
                ),
                'defaults' => array(
                    'controller' => "$moduleName\Controller\\$controllerName",
                    'action' => 'index',
                ),
            ),
        );
        $moduleConfig['router']['routes'][strtolower($controllerName)] = $controllerRouter;
        $handle = fopen('module.config.php', 'w+');
        $content = "<?php \n return array(\n";
        $content .= $this->writeConfig($moduleConfig);
        $content .= ");";
        fwrite($handle, $content);
        return;
    }

    protected function getModuleConfig($moduleName)
    {
        $this->switchToRootDir();
        chdir("module/$moduleName/config");
        return include 'module.config.php';
    }

    protected function addViewFolderForController($controllerName, $moduleName)
    {
        $this->switchToRootDir();
        chdir("module/$moduleName/view/" . strtolower($moduleName));
        mkdir(strtolower($controllerName), 0777);
        chdir(strtolower($controllerName));
        $handle = fopen('index.phtml', 'w+');
        fwrite($handle, 'hello world :)');
    }

    protected function addModuleInProject($moduleName)
    {
        $applicationConfig = $this->getApplicationConfig();
        if (!in_array($moduleName, $applicationConfig['modules'])) {
            $applicationConfig['modules'][] = $moduleName;
            $handle = fopen('application.config.php', 'w+');
            $content = "<?php \n return array(\n";
            $content .= $this->writeConfig($applicationConfig);
            $content .= ");";
            fwrite($handle, $content);
        }
    }

    protected function getApplicationConfig()
    {
        $this->switchToRootDir();
        chdir('config');
        return include 'application.config.php';
    }
    
    protected function addClassMapFile($moduleName){
        
    }

}

