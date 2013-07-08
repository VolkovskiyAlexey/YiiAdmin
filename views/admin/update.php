<?
/**
 * @var $this BaseAdminController
 * @var $model CModel
 * @var $form TbForm
 */

$this->breadcrumbs = array($this->pageTitle() => array('page', 'page' => $this->currentPageName), $model->isNewRecord ? 'Новая запись' : 'Редактирование');
$this->pageTitle = Yii::app()->name . ' - ' . $this->pageTitle();
echo $this->htmlBefore;
$this->widget('bootstrap.widgets.TbAlert');
Yii::import('bootstrap.widgets.TbForm');
$options = array(
	'type' => 'horizontal',
);
$form = TbForm::createForm($this->getModelFormConfig($model), $this, $options, $model);
echo $form->render();
echo $this->htmlAfter;

