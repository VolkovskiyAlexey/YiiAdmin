<?php
/* @var $this BaseAdminController */
/* @var $code string */
/* @var $message string */

$this->pageTitle = Yii::app()->name . ' - Ошибка ' . $code;
$this->breadcrumbs = array('Ошибка');
?>
<div class="alert alert-error"><?= "<b>Ошибка {$code}</b>. " . CHtml::encode($message) ?></div>
