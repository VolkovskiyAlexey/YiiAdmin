<?
/**
 * @var $this AdminController
 * @var $model CModel
 */

$this->breadcrumbs = array($this->pageTitle());
$this->pageTitle = Yii::app()->name . ' - ' . $this->pageTitle();


$this->widget('bootstrap.widgets.TbButton', array(
	'label' => 'Новая запись',
	'type' => 'primary', // null, 'primary', 'info', 'success', 'warning', 'danger' or 'inverse'
	'icon' => 'plus white',
	'url' => array('update', 'page' => $this->currentPageName),
));

$this->widget('bootstrap.widgets.TbGridView', $this->getGridConfig($model));

