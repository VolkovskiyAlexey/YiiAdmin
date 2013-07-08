<?php

class BaseAdminController extends CController
{

	const ATTR_TYPE_BOOLEAN = 'boolean';
	const ATTR_TYPE_STRING = 'text';
	const ATTR_TYPE_TEXT = 'text';
	const ATTR_TYPE_INTEGER = 'integer';
	const ATTR_TYPE_FK = 'fk';
	const ATTR_TYPE_HAS_MANY = 'many';
	const ATTR_TYPE_FILE = 'file';
	const ATTR_TYPE_DATETIME = 'datetime';
	const ATTR_TYPE_EMAIL = 'email';
	const ATTR_TYPE_IMAGE = 'image';
	const ATTR_TYPE_DISABLED = 'disabled';
	const ATTR_TYPE_DROPDOWN = 'dropDownList';

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
		'columnsIgnore' => array('<attribute1>', '<attributeN>'), // не обязательно, скрыть выбранные атрибуты в таблице
		'elementsIgnore' => array('<attribute1>', '<attributeN>'),// не обязательно, скрыть выбранные атрибуты в форме
		'elementsDisabled' => array('<attribute1>', '<attributeN>'),// не обязательно, отключить выбранные атрибуты в форме
		'denyCreate' => false,// не обязательно, запретить создание записи
		'denyDelete' => false,// не обязательно, запретить удаление записи
	);

	/** @var string Путь к моделям по умолчанию */
	public $defModelsPath = 'application.models.*';

	/* Сценарии для модели */
	public $updateScenario = 'admin';
	public $listScenario = 'search';

	public $htmlBefore = '';
	public $htmlAfter = '';

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
	 * @return CActiveRecord
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
	 * @param CActiveRecord $model
	 * @param $attribute
	 * @param string $relationType
	 * @return array|null
	 */
	public function getModelRelation(CActiveRecord $model, $attribute, $relationType = CActiveRecord::BELONGS_TO)
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
	public function getModelTitleAttribute(CActiveRecord $model)
	{
		$attr = $model->attributeLabels();
		$options = array();
		foreach($attr as $attribute => $label)
			if ($this->getModelAttributeType($model, $attribute, $options) == self::ATTR_TYPE_STRING)
				return $attribute;

		return key($attr);
	}

	/**
	 * Получить тип атрибута
	 *
	 * @param CActiveRecord $model
	 * @param $attribute
	 * @return string по умолчанию ATTR_TYPE_STRING
	 */
	public function getModelAttributeType(CActiveRecord $model, $attribute, &$options = array())
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
				/*
				case 'timestamp':
				case 'datetime':
					return self::ATTR_TYPE_DATETIME;*/
			}
		}

		$relations = $model->relations();
		if (!empty($relations[$attribute]) && $relations[$attribute][0] == CActiveRecord::HAS_MANY)
		{
			$rel = $relations[$attribute];
			$options['model'] = $rel[1];
			$options['relation'] = $attribute;

			return self::ATTR_TYPE_HAS_MANY;
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


	/**
	 * Получить URL к файлу
	 *
	 * @param CActiveRecord $model
	 * @param $attribute
	 * @param bool $preview true - если запрашивается версия файла для предварительного просмотра (например миниатюра)
	 * @return string
	 */
	public function getFileUrl(CActiveRecord &$model, $attribute)
	{
		return $model->{$attribute};
	}

	/**
	 * Получить URL к изображению
	 *
	 * @param CActiveRecord $model
	 * @param $attribute
	 * @param bool $preview true - если запрашивается версия файла для предварительного просмотра (например миниатюра)
	 * @return string
	 */
	public function getImageUrl(CActiveRecord &$model, $attribute, $preview = false)
	{
		return $model->{$attribute};
	}

	/**
	 * Удалить файл
	 *
	 * @param CActiveRecord $model
	 * @param $attribute
	 */
	public function deleteFile(CActiveRecord &$model, $attribute)
	{
		$model->{$attribute} = '';
	}

	/**
	 * Получить список элементов
	 *
	 * @param CActiveRecord $model
	 * @param $attribute
	 * @return mixed|null
	 */
	public function getAttributeList(CActiveRecord &$model, $attribute, $key = false)
	{
		$items = null;

		$vars = get_class_vars(get_class($model));
		if (!empty($vars['attrOptions']) && !empty($vars['attrOptions'][$attribute]))
			$items = $vars['attrOptions'][$attribute];
		else if (method_exists($model, $attribute . 'List'))
			$items = call_user_func(array($model, $attribute . 'List'));
		else if (method_exists($model, $attribute . 'sList'))
			$items = call_user_func(array($model, $attribute . 'sList'));

		if ($items == null) return null;
		if ($key === false) return $items;
		if (!empty($items[$key])) return $items[$key];

		return null;
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


	public function prepareFormConfig($config, CActiveRecord &$model)
	{
		foreach($config['elements'] as $attribute => &$element)
			if (is_array($element) && !empty($element['type']) && method_exists($this, 'element' . $element['type']))
				$element = call_user_func(array($this, 'element' . $element['type']), $model, $attribute, $element);

		$tmpElementsConfig = $config['elements'];
		foreach ($tmpElementsConfig as $name => $elementConfig)
		{
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
		$config['elements'] = $elementsConfig;
		return $config;
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

	protected function getModelAttributes(CActiveRecord &$model, $attributesType = 'elements')
	{
		$attributes = $model->attributeLabels();
		unset($attributes['id']);
		$attributes = array_keys($attributes);

		if (!empty($this->currentPage[$attributesType]))
			$attributes = $this->currentPage[$attributesType];

		if (!empty($this->currentPage[$attributesType . 'Ignore']))
		{
			foreach($this->currentPage[$attributesType . 'Ignore'] as $attribute)
			{
				$index = array_search($attribute, $attributes);
				if ($index !== false) unset($attributes[$index]);
			}
		}

		return $attributes;
	}

	//----------------------------------------------------------------------------------------------------
	// FormBuilder Elements
	// методы возвращают конфигурацию элемента для FormBuilder или html код элемента
	//----------------------------------------------------------------------------------------------------

	public function elementSubModels(CActiveRecord &$model, $attribute, $options = array())
	{
		// получаем информацию о реляции
		$relations = $model->relations();
		$subModelName = $relations[$attribute][1];
		$relAttribute = $relations[$attribute][2];

		/** @var CActiveRecord[] $subModels */
		$newSubModel = new $subModelName;
		$newSubModel->{$relAttribute} = $model->id;
		$titleAttribute = $this->getModelTitleAttribute($newSubModel);

		$subModels[] = $newSubModel;
		$subModels = array_merge($subModels, $model->{$attribute});

		$data = '';
		$modals = '';

		foreach($subModels as $subModel)
		{
			$modalId = $attribute. '-' . ($subModel->isNewRecord ? 'new' : $subModel->id);
			$modalTitle = ($subModel->isNewRecord ? 'Новая запись' : $subModel->{$titleAttribute});

			// TODO: нужен другой способ выводить ошибки
			if (!empty($_POST['subModel']['errors']) && !empty($_POST['subModel']['model']) && $_POST['subModel']['model'] == $subModelName && $_POST['subModel']['id'] == $subModel->id)
			{
				$subModel->addErrors($_POST['subModel']['errors']);
				$subModel->attributes = $_POST[$subModelName];
				Yii::app()->clientScript->registerScript('subModel-error', '$("a[href=#' . $modalId . ']").click()');
			}

			if ($model->isNewRecord)
			{
				$data .=
					'<div style="margin-bottom: 5px"><span class="btn btn-small btn-primary disabled">' . $modalTitle . '</span></div>' .
					'<div class="help-block"">Функция станет доступна после сохранения данных</div>';
			}
			else
			{
				$formConfig = $this->getModelFormConfig($subModel);
				unset($formConfig['elements'][$relAttribute]);
				$formConfig['elements'][$relAttribute] = array('type' => 'hidden');
				$formConfig['elements']['id'] = array('type' => 'hidden');
				$formConfig['elements'][] =
					CHtml::hiddenField('subModel[model]', $subModelName) .
					CHtml::hiddenField('subModel[id]', $subModel->id);

				/** @var TbForm $form */
				$form = TbForm::createForm($formConfig, $this, array('type' => 'horizontal'), $subModel);

				$data .= '<div style="margin-bottom: 5px"><a href="#' . $modalId . '" role="button" class="btn btn-small ' . ($subModel->hasErrors() ? 'btn-warning' : ($subModel->isNewRecord ? 'btn-primary' : '')) . '" data-toggle="modal">' . $modalTitle . '</a></div>';
				$modals .=
					'<div id="' . $modalId . '" class="modal ' . ($subModel->hasErrors() ? '' : 'hide') . ' fade" tabindex="-1" role="dialog" aria-hidden="true">' .
						'<div class="modal-header"><h3>' . $model->getAttributeLabel($attribute) . ': ' . $modalTitle . '</h3></div>' .
						$form->renderBegin() .
						'<div class="modal-body">' .
							$form->renderElements() .
						'</div>' .
						'<div class="modal-footer" style="margin-bottom: -20px;">' .
							str_replace('form-actions', '', $form->renderButtons()) .
						'</div>' .
						$form->renderEnd() .
					'</div>';
			}
		}

		$this->htmlAfter .= $modals;

		return $this->formControlGroup($model->getAttributeLabel($attribute), $data);
	}

	public function elementDropDownList(CActiveRecord &$model, $attribute, $options = array())
	{
		if (empty($options['items']))
			$options['items'] = $this->getAttributeList($model, $attribute);

		return array_merge(array('type' => 'dropdownlist'), $options);
	}
	public function elementRaw(CActiveRecord &$model, $attribute, $options = array())
	{
		$label =  !empty($options['label']) ? $options['label'] : $model->getAttributeLabel($attribute);
		$value = !empty($options['value']) ? $options['value'] : $model->{$attribute};
		$element = "<div class='control-group'><label class='control-label'>{$label}</label><div style='padding: 5px 0px' class='controls'>{$value}</div></div>";
		return $element;
	}
	public function elementDateTime(CActiveRecord &$model, $attribute, $options = array())
	{
		$element = array(
			'type' => 'TbDateTime',
			'options' => array(
				'format' => 'yyyy-mm-dd'
			),
		);
		return array_merge($element, $options);
	}
	public function elementChosenMultiple(CActiveRecord &$model, $attribute, $options = array())
	{
		$element = array(
			'type' => 'select2',
			'value' => implode(',', array_values($options['value'])),
			'asDropDownList' => false,
			'options' => array(
				'tokenSeparators' => array(' ', ','),
				'tags' => $options['value'],
				'width' => '100%',
			),
		);
		return array_merge($element, $options);
	}
	public function elementBoolean(CActiveRecord &$model, $attribute, $options = array()) {return array_merge(array('type' => 'TbToggleButton'), $options);}
	public function elementDisabled(CActiveRecord &$model, $attribute, $options = array()) {return array_merge(array('type' => 'disabled'), $options);}
	public function elementEmail(CActiveRecord &$model, $attribute, $options = array()) {return array_merge(array('type' => 'text', 'prepend' => '@'), $options);}
	public function elementWysiwyg(CActiveRecord &$model, $attribute, $options = array()) {return array_merge(array('type' => 'redactor'), $options);}
	public function elementFile(CActiveRecord &$model, $attribute, $options = array())
	{
		$element = $this->elementRaw($model, $attribute, array('value' => 'Файл отсутствует'));
		if (empty($options['readOnly']))
			$element = array($attribute => array('type' =>  'file'));

		if ($model->{$attribute})
		{
			if (is_string($element)) $element = array();
			$url = $this->getFileUrl($model, $attribute);
			$html = "<a class='btn ' href='{$url}'><i class='icon-arrow-down'></i>Скачать</a>";
			if (empty($options['readOnly']))
				$html .= "<label style='margin-top:10px'><input style='margin: 4px 4px 4px 0px;' type='checkbox' name='deleteFile[{$attribute}]' value='1'>удалить</label>";
			$element[$attribute . 'Download'] = $this->formControlGroup('', $html);
		}
		return $element;
	}

	public function elementImage(CActiveRecord &$model, $attribute, $options = array())
	{
		$element = $this->elementRaw($model, $attribute, array('value' => 'Файл отсутствует'));
		if (empty($options['readOnly']))
			$element = array($attribute => array('type' =>  'file'));

		if ($model->{$attribute})
		{
			if (is_string($element)) $element = array();
			$url = $this->getImageUrl($model, $attribute);
			$urlPreview = $this->getImageUrl($model, $attribute, true);
			$html = "<a class='thumbnail' target='_blank' style='display:inline-block;' href='{$url}'><img src='{$urlPreview}' style='max-height:100px'></a>";

			if (empty($options['readOnly']))
				$html.="<label style='margin-top:10px'><input style='margin: 4px 4px 4px 0px;' type='checkbox' name='deleteFile[{$attribute}]' value='1'>удалить</label>";

			$element[$attribute . 'Download'] = $this->formControlGroup(empty($options['readOnly']) ? '' : $model->getAttributeLabel($attribute), $html);
		}
		return $element;
	}

	public function elementFk(CActiveRecord &$model, $attribute, $options = array())
	{
		$relOptions = $this->getModelRelation($model, $attribute);
		$relModel = $this->loadModel($relOptions['model']);
		$relModelTitleAttribute = $this->getModelTitleAttribute($relModel);

		if (!empty($options['readOnly']))
		{
			$relation = $model->{$relOptions['relation']};
			$value = $relation ? $relation->{$relModelTitleAttribute} : '';
			return $this->elementRaw($model, $attribute, array('value' => $value));
		}

		$relModels = $relModel->findAll();
		return array('type' => 'dropdownlist', 'items' =>  CHtml::listData($relModels, 'id', $relModelTitleAttribute));
	}

	public function elementMany(CActiveRecord &$model, $attribute, $options = array())
	{
		$relOptions = $this->getModelRelation($model, $attribute);
		$relModel = $this->loadModel($relOptions['model']);
		$relModelTitleAttribute = $this->getModelTitleAttribute($relModel);
		$relModels = $model->{$attribute};
		$data = CHtml::listData($relModels, 'id', $relModelTitleAttribute);

		return array('type' => 'chosenMultiple', 'value' => $data);
	}

	//----------------------------------------------------------------------------------------------------
	// FormBuilder Config
	//----------------------------------------------------------------------------------------------------



	/**
	 * Получить конфигурацию по умолчанию для элемента FormBuilder
	 *
	 * @param CActiveRecord $model
	 * @param $attribute
	 * @return array
	 */
	public function getFormElementDefConfig(CActiveRecord &$model, $attribute)
	{
		$options = array();
		$attrType = $this->getModelAttributeType($model, $attribute, $options);

		if ($items = $this->getAttributeList($model, $attribute))
			return $this->elementDropDownList($model, $attribute, array('items' => $items));

		$element['type'] = $attrType;

		if (!empty($this->currentPage['elementsDisabled']) && in_array($attribute, $this->currentPage['elementsDisabled']))
			return array_merge($element, array('disabled' => true));

		return $element;
	}

	/**
	 * Получить конфигурацию для элемента FormBuilder
	 *
	 * @param CActiveRecord $model
	 * @param $attribute
	 * @return array
	 */
	public function getFormElementConfig(CActiveRecord &$model, $attribute)
	{
		return $this->getFormElementDefConfig($model, $attribute);
	}

	public function getElementsConfig(CActiveRecord &$model)
	{
		if (method_exists($model, 'getElementsConfig'))
			$tmpElementsConfig = call_user_func(array($model, 'getElementsConfig'));
		else
		{
			$elements = $this->getModelAttributes($model, 'elements');
			foreach ($elements as $name)
				$tmpElementsConfig[$name] = $this->getFormElementConfig($model, $name);
		}

		return $tmpElementsConfig;
	}

	/**
	 * Конфигурация для FormBuilder по умолчанию
	 *
	 * @param $model
	 * @return array
	 */
	public function getModelFormDefConfig(CActiveRecord &$model)
	{
		$elementsConfig = $this->getElementsConfig($model);

		$config = array(
			'showErrorSummary' => true,
			'elements' => $elementsConfig,
			'buttons' => array(
				'submit' => null,
				'add' => null,
				'reset' => array(
					'type' => 'reset',
					'label' => 'Сбросить',
				),
			),
			'enctype' => 'multipart/form-data',
			'method' => 'post',
		);

		if ($this->checkModelAccess('edit') || $this->checkModelAccess('create'))
			$config['buttons']['submit'] = array(
				'type' => 'submit',
				'layoutType' => 'primary',
				'label' => 'Сохранить',
			);

		if (!$model->isNewRecord && $this->checkModelAccess('delete'))
			$config['buttons']['delete'] = array(
				'type' => 'submit',
				'layoutType' => 'danger',
				'label' => 'Удалить',
				'htmlOptions' => array('confirm' => 'Точно удалить запись?')
			);


		if ($model->isNewRecord && $this->checkModelAccess('create'))
			$config['buttons']['add'] = array(
				'type' => 'submit',
				'layoutType' => 'info',
				'label' => 'Сохранить и добавить еще',
			);


		return $config;
	}

	/**
	 * Конфигурация для FormBuilder
	 *
	 * @param CActiveRecord $model
	 * @return array
	 */
	public function getModelFormConfig(CActiveRecord &$model)
	{

		$method = 'get'. get_class($model) .'FormConfig';
		if (method_exists($this, $method))
			return $this->prepareFormConfig(call_user_func(array($this, $method), $model), $model);

		$defFormConfig = $this->getModelFormDefConfig($model);
		return $this->prepareFormConfig(method_exists($model, 'getFormConfig') ? $model->getFormConfig($defFormConfig) : $defFormConfig, $model);
	}


	//----------------------------------------------------------------------------------------------------
	// Grid Columns
	//----------------------------------------------------------------------------------------------------


	public function columnLink(CActiveRecord &$model, $attribute, $options = array())
	{
		if (empty($options['name'])) $options['name'] = $attribute;
		$options['value'] = !empty($options['value']) ? $options['value'] : '$data->' . $attribute;
		$options['value'] = sprintf('CHtml::link(%1$s, array("update", "page" => "%2$s", "id"=>$data->id))', $options['value'], $this->currentPageName);
		$options['type'] = 'raw';
		unset($options['link']);

		return $options;
	}

	public function columnText(CActiveRecord &$model, $attribute, $options = array()) {
		$value = !empty($options['value']) ? $options['value'] : sprintf(get_class($this) . '::shorter($data->%1$s)', $attribute);

		$column = array(
			'name' => $attribute,
			'value' => $value,
		);
		if (!empty($options['link']))
			$column = $this->columnLink($model, $attribute, $options);

		return array_merge($column, $options);
	}

	public function columnDropDownList(CActiveRecord &$model, $attribute, $options = array()) {
		if (empty($options['filter']))
			$options['filter'] = $this->getAttributeList($model, $attribute);

		$options['type'] = 'text';

		$column = array(
			'name' => $attribute,
			'value' => sprintf('!empty($this->filter["$data->%1$s"]) ? $this->filter["$data->%1$s"] : ""', $attribute)
		);
		return array_merge($column, $options);
	}

	public function columnImage(CActiveRecord &$model, $attribute, $options = array()) {
		if (empty($options['value'])) $options['value'] = sprintf('Yii::app()->controller->getImageUrl($data, "%1$s", true)', $attribute);
		if (empty($options['width'])) $options['width'] = 50;

		$column = array(
			'type' => 'raw',
			'filter' => false,
			'name' => $attribute,
			'htmlOptions' => array('width' => $options['width']),
			'value' => sprintf('is_null($data->%1$s) ? "" : \'<img width="%3$s" src="\' . %2$s . \'">\'', $attribute, $options['value'], $options['width']),
		);
		unset($options['width']);

		if (!empty($options['link']))
			$options = $this->columnLink($model, $attribute, $options);

		return array_merge($options, $column);
	}

	public function columnFk(CActiveRecord &$model, $attribute, $options = array())
	{
		$relOptions = $this->getModelRelation($model, $attribute);
		$relModel = $this->loadModel($relOptions['model']);
		$relModelTitleAttribute = $this->getModelTitleAttribute($relModel);
		$model = $model->with($relOptions['relation']);

		$column = array(
			'filter' => CHtml::listData($relModel->findAll(), 'id', $relModelTitleAttribute),
			'name' => $attribute,
			'value' => sprintf('!empty($data->%1$s) ? $data->%1$s->%2$s : ""', $relOptions['relation'], $relModelTitleAttribute)
		);
		return $column;
	}

	public function columnMany(CActiveRecord &$model, $attribute, $options = array())
	{
		$relOptions = $this->getModelRelation($model, $attribute);
		$relModel = $this->loadModel($relOptions['model']);
		$relModelTitleAttribute = $this->getModelTitleAttribute($relModel);

		$column = array(
			'name' => $attribute,
			'filter' => false,
			'value' => sprintf('implode(", ", array_values(CHtml::listData($data->%1$s, "id", "%2$s")))', $attribute, $relModelTitleAttribute) ,
		);
		return $column;
	}

	public function columnBoolean(CActiveRecord &$model, $attribute, $options = array())
	{
		$config = array(
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
		return array_merge($config, $options);
	}

	public function columnEditable(CActiveRecord &$model, $attribute, $options = array())
	{
		$config = array(
			'name' => $attribute,
			'class' => 'bootstrap.widgets.TbEditableColumn',
			'editable' => array(
				'url' => $this->createUrl('update', array('page' => $this->currentPageName, 'editable' => true)),
			),
		);
		if (!empty($options['filter']))
		{
			$config['editable']['type'] = 'select';
			$config['editable']['source'] = $options['filter'];
		}


		return array_merge($config, $options);
	}

	public function prepareGridConfig($config, CActiveRecord &$model)
	{
		foreach($config['columns'] as $attribute => &$options)
		{
			if (is_array($options) && !empty($options['type']) && method_exists($this, 'column' . $options['type']))
				$options = call_user_func(array($this, 'column' . $options['type']), $model, $attribute, $options);

			// наследование параметров
			if (is_array($options))
			{
				$matches = preg_grep('/^column(\w+)/i', array_keys($options));
				foreach($matches as $column)
				{
					$extendedOptions = $options[$column] === true ? $options : $options[$column];
					unset($extendedOptions[$column]);
					$options = call_user_func(array($this, $column), $model, $attribute, $extendedOptions);
				}
			}
		}

		return $config;
	}



	//----------------------------------------------------------------------------------------------------
	// Grid Config
	//----------------------------------------------------------------------------------------------------


	/**
	 * Конфигурация по умолчанию для колонки
	 *
	 * @param CActiveRecord $model
	 * @param $attribute
	 * @return array
	 */
	public function getGridColumnDefConfig(CActiveRecord &$model, $attribute)
	{
		$options = array();
		$attrType = $this->getModelAttributeType($model, $attribute, $options);
		$column['type'] = $attrType;
		$column['name'] = $attribute;
		return $column;
	}

	/**
	 * Конфигурация для колонки
	 *
	 * @param CActiveRecord $model
	 * @param $attribute
	 * @return array
	 */
	public function getGridColumnConfig(CActiveRecord &$model, $attribute)
	{
		$config = $this->getGridColumnDefConfig($model, $attribute);
		return $config;
	}


	public function getColumnsConfig(CActiveRecord &$model)
	{
		if (method_exists($model, 'getColumnsConfig'))
			return call_user_func(array($model, 'getColumnsConfig'));

		$columns = $this->getModelAttributes($model, 'columns');
		$columnsConfig = array();

		foreach($columns as $name)
		{
			$columnConfig = $this->getGridColumnConfig($model, $name);
			if (!empty($columnConfig)) $columnsConfig[$name] = $columnConfig;
		}
		return $columnsConfig;
	}

	/**
	 * Конфигурация по умолчанию для таблицы
	 *
	 * @param CActiveRecord $model
	 * @return array
	 */
	public function getGridDefConfig(CActiveRecord &$model)
	{
		$buttons = array(
			'class' => 'bootstrap.widgets.TbButtonColumn',
			'template' => '{update}' . ($this->checkModelAccess('delete') ? '{delete}' : ''),
			'htmlOptions' => array('nowrap' => 'nowrap'),
			'buttons' => array(
				'update' => array('url' => 'array("update", "page" => "' . $this->currentPageName . '", "id"=>$data->id)'),
				'delete' => array('url' => 'array("delete", "page" => "' . $this->currentPageName . '", "id"=>$data->id)'),
			),
		);
		$columnsConfig = $this->getColumnsConfig($model);

		$columnsConfig['buttons'] = !empty($columnsConfig['buttons']) ? array_merge($buttons, $columnsConfig['buttons']) : $buttons;

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
	 * @param CActiveRecord $model
	 * @return array
	 */
	public function getGridConfig(CActiveRecord &$model)
	{
		$config = method_exists($model, 'getGridConfig') ? call_user_func(array($model, 'getGridConfig')) : $this->getGridDefConfig($model);
		return $this->prepareGridConfig($config, $model);
	}


	/**
	 * Проверить доступ
	 *
	 * @param $operation
	 */
	public function checkModelAccess($operation)
	{
		$operations = array(
			'create' => empty($this->currentPage['denyCreate']),
			'delete' => empty($this->currentPage['denyDelete']),
			'edit' => empty($this->currentPage['denyEdit']),
		);

		return $operations[$operation];
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
	}


	//****************************************************************************************************
	// События
	//****************************************************************************************************


	public function beforeAction($action)
	{
		// если accessControl пропустил нас, меняем обработчик ошибок на наш
		Yii::app()->errorHandler->errorAction = Yii::app()->controller->id . '/error';
		return true;
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
		$model->scenario = $this->listScenario;
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

		$baseModelName = $modelName;
		$baseModelId = $id;

		if (!empty($_POST['subModel']['model']))
		{
			$modelName = $_POST['subModel']['model'];
			$id = $_POST['subModel']['id'];
		}


		// если изменили значение в ячейке таблицы
		if (Yii::app()->request->isAjaxRequest && Yii::app()->request->getParam('editable'))
		{
			Yii::import('bootstrap.widgets.TbEditableSaver');
			$es = new TbEditableSaver($modelName);
			$es->scenario = $this->updateScenario;
			$es->update();
			Yii::app()->end();
		}

		// загружаем модель с которой работаем
		$model = $this->loadModel($modelName, $id);
		$model->setScenario($this->updateScenario);

		// загружаем базовую модель
		$baseModel = $baseModelName == $modelName ? $model : $this->loadModel($baseModelName, $baseModelId);

		if (isset($_POST['delete']) && $id)
		{
			// загружаем модель для удаления, без этого события beforeDelete, afterDelete не работают
			if ($modelDeleted = $model->delete($id) && $baseModelName == $modelName)
				$this->redirect(array('page', 'page' => $this->currentPageName));
		}

		if (!empty($_POST[$modelName]) && empty($modelDeleted)) {
			$model->attributes = $_POST[$modelName];

			// ajax валидация
			if(Yii::app()->getRequest()->getIsAjaxRequest())
			{
				echo CActiveForm::validate($model);
				Yii::app()->end();
			}

			// удаление файлов
			if (!empty($_POST['deleteFile']))
				foreach($_POST['deleteFile'] as $attribute => $val)
					$this->deleteFile($model, $attribute);

			$isNewRecord = $model->isNewRecord;

			if ($model->save())
			{
				Yii::app()->user->setFlash('success', 'Изменения успешно сохранены.');
				if ($isNewRecord && $baseModelName == $modelName)
				{
					if (isset($_POST['add']))
						$this->redirect(array('update', 'page' => $this->currentPageName));
					else
						$this->redirect(array('page', 'page' => $this->currentPageName));
				}
			}
			else if ($baseModelName != $modelName)
				$_POST['subModel']['errors'] = $model->errors;

		}

		if (Yii::app()->request->isAjaxRequest)
			die(CJSON::encode($model->errors));

		$this->render('update', array('model' => $baseModel));
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
		if (!$this->checkModelAccess('delete')) return false;

		// загружаем модель для удаления, без этого события beforeDelete, afterDelete не работают
		$model = $this->loadModel($modelName, $id);
		$model->delete();
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
