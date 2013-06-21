<?php

class BaseAdminController extends CController
{

	const ATTR_TYPE_BOOLEAN = 'boolean';
	const ATTR_TYPE_STRING = 'string';
	const ATTR_TYPE_TEXT = 'text';
	const ATTR_TYPE_INTEGER = 'integer';
	const ATTR_TYPE_FK = 'fk';
	const ATTR_TYPE_FILE = 'file';
	const ATTR_TYPE_DATETIME = 'datetime';
	const ATTR_TYPE_EMAIL = 'email';
	const ATTR_TYPE_IMAGE = 'image';

	public $layout = '//layouts/admin';
	public $breadcrumbs = array();

	/**
	 * @var array Разрешенные страницы.
	 * @see {$currentPage}
	 */
	public $pages = array();

	/** @var string Имя текущей страницы */
	public $currentPageName = '';

	/** @var string Текущая страница */
	public $currentPage = array(
		// общие параметры
		'icon' => '<icon>', // не обязательно, иконка для меню
		'title' => '<tite>', // не обязательно, не показывать выбранные колонки в таблице

		// ВАЖНО: страницу можно привязать к модели, ИЛИ к действию
		// у привязок могут быть свои параметры

		// привязка в действию
		'action' => '<controllerId>/<actionId>', // привязка к действию

		// привязка к модели
		'model' => '<modeName>',
		'ignoreColumns' => array('<attribute1>', '<attributeN>'), // не обязательно, скрыть выбранные атрибуты в таблице
		'ignoreElements' => array('<attribute1>', '<attributeN>'),// не обязательно, скрыть выбранные атрибуты в форме
	);

	/** @var string Путь к моделям по умолчанию */
	public $defModelsPath = 'application.models.*';

	public function filters()
	{
		return array('accessControl');
	}

	public function accessRules()
	{
		return array();
	}


	//****************************************************************************************************
	// Модели
	//****************************************************************************************************


	/**
	 * Загрузить модель
	 *
	 * @param $name
	 * @param null $pk
	 * @return CModel
	 */
	public function loadModel($name, $pk = null)
	{
		$name = (string)$name;
		$model = new $name;
		if ($pk) $model = $model->findByPk((int)$pk);
		return $model;
	}

	/**
	 * Получить список моделей
	 *
	 * @param string $modelsPath
	 * @return array
	 */
	public function getModelsList($modelsPath = null)
	{
		if (is_null($modelsPath)) $modelsPath = $this->defModelsPath;

		$models = array();
		$files = CFileHelper::findFiles(Yii::getPathOfAlias($modelsPath), array('fileTypes' => array('php')));
		if ($files) {
			foreach ($files as $file) {
				$models[] = str_replace('.php', '', substr(strrchr($file, DIRECTORY_SEPARATOR), 1));
			}
		}

		return $models;
	}

	/**
	 * Получить имя реляции, которая использует указанный атрибут как внешний ключ
	 *
	 * @param CModel $model
	 * @param $attribute
	 * @param string $relationType
	 * @return array|null
	 */
	public function getModelRelation(CModel $model, $attribute, $relationType = CActiveRecord::BELONGS_TO)
	{
		$relations = $model->relations();
		foreach ($relations as $relation => $options)
		{
			list($relType, $relModel, $relAttribute) = $options;
			if ($relType != $relationType || $attribute != $relAttribute) continue;

			return array(
				'relation' => $relation,
				'model' => $relModel,
				'options' => $options
			);
		}
		return null;
	}

	/**
	 * Получить атрибут для генерации заголовка записи модели
	 *
	 * по умолчанию 1-й атрибут не id из массива attributeLabels()
	 *
	 * @param $model
	 * @return mixed
	 */
	public function getModelTitleAttribute(CModel $model)
	{
		$attr = $model->attributeLabels();
		unset($attr['id']);
		return key($attr);
	}

	/**
	 * Получить тип атрибута
	 *
	 * @param CModel $model
	 * @param $attribute
	 * @return string по умолчанию ATTR_TYPE_STRING
	 */
	public function getModelAttributeType(CModel $model, $attribute, &$options = array())
	{
		/** @var $meta CActiveRecordMetaData */
		$metaData = $model->getMetaData();

		if (isset($metaData->columns[$attribute]))
		{
			/** @var $column CMysqlColumnSchema */
			$column = $metaData->columns[$attribute];

			// если это внешний ключ
			if ($column->isForeignKey)
				if ($options = $this->getModelRelation($model, $attribute)) return self::ATTR_TYPE_FK;

			// узнаем тип данных из БД
			switch ($column->dbType)
			{
				case 'text':
				case 'mediumtext':
				case 'longtext':
					return self::ATTR_TYPE_TEXT;

				case 'timestamp':
				case 'datetime':
					return self::ATTR_TYPE_DATETIME;
			}
		}

		foreach($model->rules() as $rule)
		{
			list($attributes, $validator) = $rule;
			if (strpos($attributes, $attribute) === false) continue;
			$validators[$validator] = $rule;
		}

		// узнаем тип данных из правил валидаци
		if (isset($validators['boolean']))
		{
			$options = $validators['boolean'];
			return self::ATTR_TYPE_BOOLEAN;
		}

		if (isset($validators['email']))
		{
			$options = $validators['email'];
			return self::ATTR_TYPE_EMAIL;
		}

		if (isset($validators['file']))
		{
			$options = $validators['file'];
			if (!empty($options['types']) && preg_match('/(jpg|jpeg|jpe|gif|png|bmp)[, ]?/', strtolower($options['types'])))
				return self::ATTR_TYPE_IMAGE;

			return self::ATTR_TYPE_FILE;
		}

		if (isset($validators['length']))
		{
			$options = $validators['length'];
			if (!empty($options['max']) && $options['max'] > 255)
				return self::ATTR_TYPE_TEXT;
			else
				return self::ATTR_TYPE_STRING;
		}


		return self::ATTR_TYPE_STRING;
	}


	//****************************************************************************************************
	// Helpers
	//****************************************************************************************************


	public static function shorter($input, $length = 100)
	{
		//no need to trim, already shorter than trim length
		if (strlen($input) <= $length) return $input;

		//find last space within length
		$last_space = strrpos(substr($input, 0, $length), ' ');
		if(!$last_space) $last_space = $length;
		$trimmed_text = substr($input, 0, $last_space);

		//add ellipses (...)
		$trimmed_text .= '...';

		return $trimmed_text;
	}

	public function formControlGroup($label = '', $data = '')
	{
		return "<div class='control-group'><label class='control-label'>{$label}</label><div class='controls'>{$data}</div></div>";
	}

	public static function arrayGetValue($arr, $path)
	{
		if (!$path) return null;

		$segments = is_array($path) ? $path : explode('.', $path);
		$cur = &$arr;
		foreach ($segments as $segment) {
			if (!isset($cur[$segment])) return null;
			$cur = $cur[$segment];
		}

		return $cur;
	}

	//----------------------------------------------------------------------------------------------------
	// FormBuilder Config
	//----------------------------------------------------------------------------------------------------


	/**
	 * Получить конфигурацию по умолчанию для элемента FormBuilder
	 *
	 * @param CModel $model
	 * @param $attribute
	 * @return array
	 */
	public function getFormElementDefConfig(CModel $model, $attribute)
	{
		if (!empty($this->currentPage['elementsIgnore']) && in_array($attribute, $this->currentPage['elementsIgnore'])) return null;

		$options = array();
		$attrType = $this->getModelAttributeType($model, $attribute, $options);

		if ($attribute == 'password') return  array('type' => 'password', 'value' => '');

		$vars = get_class_vars(get_class($model));

		if (!empty($vars['attrOptions']) && !empty($vars['attrOptions'][$attribute]))
			return array(
				'type' => 'dropdownlist',
				'items' => $vars['attrOptions'][$attribute],
				'empty' => '',
			);

		switch ($attrType)
		{
			case self::ATTR_TYPE_BOOLEAN:
				$element = array('type' => 'TbToggleButton');
				break;
			case self::ATTR_TYPE_TEXT:
				$element = array('type' => 'redactor');
				break;
			case self::ATTR_TYPE_FK:
				$relModel = $this->loadModel($options['model']);
				$relModelTitleAttribute = $this->getModelTitleAttribute($relModel);

				$relModels = $relModel->findAll();
				$element = array(
					'type' => 'dropdownlist',
					'items' =>  CHtml::listData($relModels, 'id', $relModelTitleAttribute),
					'empty' => '',
				);
				break;
			case self::ATTR_TYPE_FILE:
				$element = array($attribute => array('type' =>  'file'));
				if ($model->{$attribute})
					$element[] = $this->formControlGroup('', "<a class='btn ' href='{$model->{$attribute}}'><i class='icon-arrow-down'></i>Скачать</a>");
				break;

			case self::ATTR_TYPE_IMAGE:
				$element = array($attribute => array('type' =>  'file'));
				if ($model->{$attribute})
					$element[] = $this->formControlGroup('', "<a class='thumbnail ' href='{$model->{$attribute}}'><img src='{$model->{$attribute}}' height='50'></a>");
				break;

			case self::ATTR_TYPE_EMAIL:
				$element = array('type' => 'text', 'prepend' => '@');
				break;

			case self::ATTR_TYPE_STRING:
				if (!empty($options['max']) && $options['max'] > 150)
					$element = array('type' => 'textarea', 'class' => 'span6');
				else
					$element = array('type' => 'text');
				break;

			default:
				$element = array('type' => 'text');
		}

		return $element;
	}

	/**
	 * Получить конфигурацию для элемента FormBuilder
	 *
	 * @param CModel $model
	 * @param $attribute
	 * @return array
	 */
	public function getFormElementConfig(CModel $model, $attribute)
	{
		return $this->getFormElementDefConfig($model, $attribute);
	}

	/**
	 * Конфигурация для FormBuilder по умолчанию
	 *
	 * @param $model
	 * @return array
	 */
	public function getModelFormDefConfig($model)
	{
		$elements = $model->attributeLabels();
		unset($elements['id']);
		$elementsConfig = array();

		foreach ($elements as $name => $element)
		{
			$elementConfig =  $this->getFormElementConfig($model, $name);
			if (is_array($elementConfig) && empty($elementConfig['type']))
			{
				foreach($elementConfig as $elName => $elConfig)
				{
					if (is_string($elName))
						$elementsConfig[$elName] = $elConfig;
					else
						$elementsConfig[] = $elConfig;
				}
			}
			else
				$elementsConfig[$name] = $elementConfig;
		}

		$config = array(
			'showErrorSummary' => true,
			'elements' => $elementsConfig,
			'buttons' => array(
				'submit' => array(
					'type' => 'submit',
					'layoutType' => 'primary',
					'label' => 'Сохранить',
				),
				'add' => null,
				'reset' => array(
					'type' => 'reset',
					'label' => 'Сбросить',
				),
			),
			'enctype' => 'multipart/form-data',
			'method' => 'post',
		);

		if (!$model->isNewRecord)
		{
			$config['buttons']['delete'] = array(
				'type' => 'submit',
				'layoutType' => 'danger',
				'label' => 'Удалить',
				'htmlOptions' => array('confirm' => 'Точно удалить запись?')
			);
		}
		else
		{
			$config['buttons']['add'] = array(
				'type' => 'submit',
				'layoutType' => 'info',
				'label' => 'Сохранить и добавить еще',
			);
		}

		return $config;
	}

	/**
	 * Конфигурация для FormBuilder
	 *
	 * @param CModel $model
	 * @return array
	 */
	public function getModelFormConfig(CModel $model)
	{
		$defFormConfig = $this->getModelFormDefConfig($model);
		return method_exists($model, 'getFormConfig') ? $model->getFormConfig($defFormConfig) : $defFormConfig;
	}


	//----------------------------------------------------------------------------------------------------
	// Grid Config
	//----------------------------------------------------------------------------------------------------


	/**
	 * Конфигурация по умолчанию для колонки
	 *
	 * @param CModel $model
	 * @param $attribute
	 * @return array
	 */
	public function getGridColumnDefConfig(CModel &$model, $attribute)
	{
		if (!empty($this->currentPage['columnsIgnore']) && in_array($attribute, $this->currentPage['columnsIgnore'])) return null;

		$options = array();

		$vars = get_class_vars(get_class($model));

		if (!empty($vars['attrOptions']) && !empty($vars['attrOptions'][$attribute]))
		{

			return array(
				'filter' => $vars['attrOptions'][$attribute],
				'name' => $attribute,
				'class' => 'bootstrap.widgets.TbEditableColumn',
				'editable' => array(
					'type' => 'select',
					'url' => $this->createUrl('update', array('page' => $this->currentPageName, 'editable' => true)),
					'source' => $vars['attrOptions'][$attribute],
				),
				'value' => sprintf(get_class($this) . '::arrayGetValue(get_class_vars(get_class($data)), "attrOptions.%1$s.$data->%1$s")', $attribute)
			);
		}


		$attrType = $this->getModelAttributeType($model, $attribute, $options);
		$column = array(
			'name' => $attribute,
			'value' => sprintf(get_class($this) . '::shorter($data->%1$s)', $attribute),
		);

		switch ($attrType)
		{
			case self::ATTR_TYPE_FK:
				$relModel = $this->loadModel($options['model']);
				$relModelTitleAttribute = $this->getModelTitleAttribute($relModel);
				$model = $model->with($options['relation']);

				$column = array(
					'filter' => CHtml::listData($relModel->findAll(), 'id', $relModelTitleAttribute),
					'name' => $attribute,
					'value' => sprintf('!empty($data->%1$s) ? $data->%1$s->%2$s : ""', $options['relation'], $relModelTitleAttribute)
				);

				break;

			case self::ATTR_TYPE_BOOLEAN:
				$column = array(
					'filter' => array('Нет', 'Да'),
					'name' => $attribute,
					'value' => sprintf('is_null($data->%1$s) ? "" : ($data->%1$s ? "Да" : "Нет")', $attribute),
					'class' => 'bootstrap.widgets.TbEditableColumn',
					'editable' => array(
						'type' => 'select',
						'url' => $this->createUrl('update', array('page' => $this->currentPageName, 'editable' => true)),
						'source' => array('Нет', 'Да'),
					),
				);

				break;

			case self::ATTR_TYPE_FILE:
			case self::ATTR_TYPE_IMAGE:
				$column = null;
				break;
		}

		return $column;
	}

	/**
	 * Конфигурация для колонки
	 *
	 * @param CModel $model
	 * @param $attribute
	 * @return array
	 */
	public function getGridColumnConfig(CModel &$model, $attribute)
	{
		$config = $this->getGridColumnDefConfig($model, $attribute);
		return $config;
	}

	/**
	 * Конфигурация по умолчанию для таблицы
	 *
	 * @param CModel $model
	 * @return array
	 */
	public function getGridDefConfig(CModel &$model)
	{
		$columns = $model->attributeLabels();
		$columnsConfig = array();

		foreach($columns as $name => $column)
		{
			$columnConfig = $this->getGridColumnConfig($model, $name);
			if (!empty($columnConfig)) $columnsConfig[$name] = $columnConfig;
		}

		$columnsConfig['buttons'] = array(
			'class' => 'bootstrap.widgets.TbButtonColumn',
			'template' => '{update}{delete}',
			'buttons' => array(
				'update' => array('url' => 'array("update", "page" => "' . $this->currentPageName . '", "id"=>$data->id)'),
				'delete' => array('url' => 'array("delete", "page" => "' . $this->currentPageName . '", "id"=>$data->id)'),
			),
		);

		return array(
			'type' => 'striped bordered condensed',
			'dataProvider' => $model->search(),
			'template' => "{items}\n{pager}",
			'filter' => $model,
			'columns' => $columnsConfig,
		);
	}

	/**
	 * Конфигурация для таблицы
	 *
	 * @param CModel $model
	 * @return array
	 */
	public function getGridConfig(CModel &$model)
	{
		$config = $this->getGridDefConfig($model);
		return method_exists($model, 'getGridConfig') ? $model->getGridConfig($config) : $config;
	}


	//****************************************************************************************************
	// Страницы
	//****************************************************************************************************


	/**
	 * Получить имя страницы связанную с моделью
	 *
	 * @param $model
	 * @return int|null|string
	 */
	public function getModelPageName($model)
	{
		foreach($this->pages as $pageName => $page)
			if (!empty($page['model']) && $page['model'] == $model) return $pageName;

		return null;
	}

	/**
	 * Получить страницы админ. панели
	 *
	 * @return array
	 */
	public function getPages()
	{
		$models = $this->getModelsList();
		$pages = array();
		foreach($models as $model)
			$pages[$model] = array('model' => $model);

		return $pages;
	}

	public function pageTitle($pageName = null)
	{
		if (is_null($pageName)) $pageName = $this->currentPageName;
		return !empty($this->pages[$pageName]['title']) ? $this->pages[$pageName]['title'] : $pageName;
	}


	//****************************************************************************************************
	// Инициализация
	//****************************************************************************************************


	public function init()
	{
		Yii::app()->bootstrap->init();
		$this->pages = $this->getPages();
		if (empty($this->pages))
			throw new CHttpException(401, 'Нет доступа');

		Yii::app()->errorHandler->errorAction = Yii::app()->controller->id . '/error';
	}


	//****************************************************************************************************
	// Действия
	//****************************************************************************************************


	/**
	 * Стартовая станица админки
	 */
	public function actionIndex()
	{
		$this->redirect(array('page', 'page' => key($this->pages)));
	}

	/**
	 * Вывод страницы админки
	 *
	 * @param $page
	 * @throws CHttpException
	 * @throws CException
	 */
	public function actionPage($page)
	{
		$this->currentPageName = $page;
		if (empty($this->pages[$page])) throw new CHttpException(404, 'Указанная запись не найдена');
		$this->currentPage = $page = $this->pages[$this->currentPageName];

		// если страница ссылается на другой action
		if (!empty($page['action']))
		{
			if (strpos($page['action'], '/'))
				Yii::app()->runController($page['action']);
			else
			{
				$pageAction = $this->createAction($page['action']);
				if (!$pageAction) throw new CException("Действие \"{$page['action']}\" не найдено");
				$pageAction->run();
			}

			return;
		}

		// если страница связана с моделью, выводим список записей
		if (!empty($page['model'])) return $this->actionList($page['model']);

		throw new CHttpException('Необходимо указать связанное действие или модель.');

	}

	/**
	 * Вывод списка записей из модели
	 *
	 * @param $model
	 */
	public function actionList($model)
	{
		$modelName = $model;
		$model = $this->loadModel($modelName);
		$model->scenario = 'search';
		$model->unsetAttributes();

		if (isset($_GET[$modelName]))
			$model->attributes = $_GET[$modelName];

		$this->render('list', array('model' => $model));
	}


	/**
	 * Редактирование модели
	 *
	 * @param $page
	 * @param null $id
	 * @throws CHttpException
	 */
	public function actionUpdate($page, $id = null)
	{
		$this->currentPageName = $page;
		if (empty($this->pages[$page])) throw new CHttpException(404, 'Указанная запись не найдена');
		$this->currentPage = $page = $this->pages[$this->currentPageName];

		$modelName = $this->pages[$this->currentPageName]['model'];

		// если изменили значение в ячейке таблицы
		if (Yii::app()->request->isAjaxRequest && Yii::app()->request->getParam('editable'))
		{
			Yii::import('bootstrap.widgets.TbEditableSaver');
			$es = new TbEditableSaver($modelName);
			$es->update();
			Yii::app()->end();
		}

		if (isset($_POST['delete']) && $id)
		{
			if ($this->loadModel($modelName)->deleteByPk($id))
				$this->redirect(array('page', 'page' => $this->currentPageName));
		}

		$model = $this->loadModel($modelName, $id);

		if (!empty($_POST[$modelName])) {
			$model->attributes = $_POST[$modelName];

			$isNewRecord = $model->isNewRecord;
			if ($model->save())
			{
				Yii::app()->user->setFlash('success', 'Изменения успешно сохранены.');
				if ($isNewRecord)
				{
					if (isset($_POST['add']))
						$this->redirect(array('update', 'page' => $this->currentPageName));
					else
						$this->redirect(array('page', 'page' => $this->currentPageName));
				}
			}
		}

		if (Yii::app()->request->isAjaxRequest)
			die(CJSON::encode($model->errors));

		$this->render('update', array('model' => $model));
	}

	/**
	 * Удаление модели
	 *
	 * @param $page
	 * @param $id
	 */
	public function actionDelete($page, $id)
	{
		$this->currentPageName = $page;
		$this->currentPage = $this->pages[$this->currentPageName];

		$modelName = $this->pages[$page]['model'];
		$model = $this->loadModel($modelName);
		$model->deleteByPk($id);
	}

	/**
	 * Выход из админки
	 */
	public function actionLogout()
	{
		Yii::app()->user->logout();
		$this->redirect('/');
	}

	/**
	 * Вывод ошибок
	 */
	public function actionError()
	{
		if ($error=Yii::app()->errorHandler->error)
		{
			if (Yii::app()->request->isAjaxRequest)
				echo $error['message'];
			else
				$this->render('error', $error);
		}
	}

}
