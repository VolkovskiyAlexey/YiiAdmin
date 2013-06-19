<?php
/**
 * @var $this AdminController
 * @var $adminItems[] CModel
 */
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ru" lang="ru">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="language" content="<?= Yii::app()->language ?>" />
	<link rel="stylesheet" type="text/css" href="<?php echo Yii::app()->request->baseUrl; ?>/css/admin.css" />
	<title><?php echo CHtml::encode($this->pageTitle); ?></title>
</head>

<body style="padding-top: 60px;padding-bottom: 40px;">

<div class="navbar navbar-inverse navbar-fixed-top">
	<div class="navbar-inner">
		<div class="container-fluid">

			<a class="brand" href="/"><?=Yii::app()->name?></a>

			<div class="nav-collapse collapse">
				<p class="navbar-text pull-right">
					Авторизован как <a href="#" class="navbar-link"><?=Yii::app()->user->name?></a>
				</p>
			</div>
		</div>
	</div>
</div>

<div class="container-fluid">
	<div class="row-fluid">
		<div class="span3">
			<div class="well" style="padding: 9px 0;">
				<?php
				$items[] = array('label'=>'Главная', 'icon'=>'home', 'url'=>array('index'), 'active' => empty($this->currentPage));
				$items[] = '';
				foreach ($this->pages as $key => $page)
				{
					$items[] = array(
						'label' => !empty($page['title']) ? $page['title'] : $key,
						'url' => array('page', 'page' => $key),
						'icon' => !empty($page['icon']) ? $page['icon'] : null,
						'active' => $this->currentPageName == $key,

					);
				}
				$items[] = '';
				$items[] = array('label'=>'Выход ('.Yii::app()->user->name.')', 'icon'=>'icon-off', 'url'=>array('logout'));
				$this->widget('bootstrap.widgets.TbMenu', array('type'=>'list','items'=> $items));
				?>
			</div>
		</div>
		<div class="span9">
			<?php $this->widget('bootstrap.widgets.TbBreadcrumbs', array(
				'homeLink' => CHtml::link('Главная', array('index')),
				'links'=>$this->breadcrumbs,
			)); ?>
			<?php echo $content; ?>
		</div>
	</div>
</div>

</body>
</html>
