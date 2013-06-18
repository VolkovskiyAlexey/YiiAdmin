<?
/**
 * @var $this BaseAdminController
 * @var $model CModel
 * @var $form TbForm
 */

$this->breadcrumbs = array($this->pageTitle() => array('page', 'page' => $this->currentPageName), $model->isNewRecord ? 'Новая запись' : 'Редактирование');
$this->pageTitle = Yii::app()->name . ' - ' . $this->pageTitle();

$this->widget('bootstrap.widgets.TbAlert');

Yii::import('bootstrap.widgets.TbForm');
$form = TbForm::createForm($this->getModelFormConfig($model), $model, array('type' => 'horizontal'));
echo $form->render();

