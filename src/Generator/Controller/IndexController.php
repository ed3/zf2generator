<?php

namespace Generator\Controller;

use Zend\Mvc\Controller\AbstractActionController,
    Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{

    public function formAction()
    {
        $dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $model = new \Generator\Model\Generator($dbAdapter);
        $modules = $model->getModules();
        $tables = $model->getDatabaseTables();
        if ($this->request->isPost()) {
            $module = $this->request->getPost('module');
            $table = $this->request->getPost('table');
            $model->generateForm($module, $table);
            $this->redirect()->toRoute('generator', array('action' => 'success', 'id' => 'Form'));
        }
        return array('tables' => $tables, 'modules' => $modules);
    }

    public function controllerAction()
    {
        $dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $model = new \Generator\Model\Generator($dbAdapter);
        $modules = $model->getModules();
        if($this->request->isPost()){
            $params = $this->request->getPost();
            if(!empty($params['controller_name'])){
                $model->createControllerForModule($params['module'], $params['controller_name']);
                $this->redirect()->toRoute('generator', array('action' => 'success', 'id' => 'Controller'));
            }
        }
        return array('modules' => $modules);
    }

    public function moduleAction()
    {
        $dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $model = new \Generator\Model\Generator($dbAdapter);
        if ($this->request->isPost()) {
            $params = $this->request->getPost();
            $createController = false;
            if (!empty($params['module_name'])) {
                $moduleName = ucfirst($params['module_name']);
                if (isset($params['create_controller'])) {
                    $createController = true;
                    if (!empty($params['controller_name']))
                        $controllerName = $params['controller_name'];
                    else
                        $controllerName = $moduleName;
                } else {
                    $controllerName = null;
                }
                $model->generateModule($moduleName, $createController, $controllerName);
                $this->redirect()->toRoute('generator', array('action' => 'success', 'id' => 'Module'));
            }
        }
    }
    
    public function indexAction()
    {
        
    }
    
    public function successAction(){
        $type = $this->getEvent()->getRouteMatch()->getParam('id');
        return array('type' => $type);
    }

}