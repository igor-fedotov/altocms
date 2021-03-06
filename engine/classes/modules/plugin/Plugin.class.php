<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS
 * @Project URI: http://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 * Based on
 *   LiveStreet Engine Social Networking by Mzhelskiy Maxim
 *   Site: www.livestreet.ru
 *   E-mail: rus.engine@gmail.com
 *----------------------------------------------------------------------------
 */

/**
 * Модуль управления плагинами
 *
 * @package engine.modules
 * @since   1.0
 */
class ModulePlugin extends Module {

    /**
     * Файл описания плагина
     *
     * @var string
     */
    const PLUGIN_XML_FILE = 'plugin.xml';

    /**
     * Путь к директории с плагинами
     *
     * @var string
     */
    protected $sPluginsCommonDir;

    protected $sPluginsAppDir;

    /**
     * Список плагинов
     *
     * @var array
     */
    protected $aPluginsList;

    /**
     * Список engine-rewrite`ов (модули, экшены, сущности, шаблоны)
     * Определяет типы объектов, которые может переопределить/унаследовать плагин
     *
     * @var array
     */
    protected $aDelegates
        = array(
            'module'   => array(),
            'mapper'   => array(),
            'action'   => array(),
            'entity'   => array(),
            'template' => array(),
            'block'    => array(),
        );

    /**
     * Стек наследований
     *
     * @var array
     */
    protected $aInherits = array();

    /**
     * Инициализация модуля
     */
    public function Init() {

        $this->sPluginsCommonDir = F::GetPluginsDir();
        $this->sPluginsAppDir = F::GetPluginsDir(true);
    }

    /**
     * Возвращает путь к папке с плагинами
     *
     * @return string
     */
    public function GetPluginsDir() {

        return $this->sPluginsCommonDir;
    }

    /**
     * Возвращает XML-манифест плагина
     *
     * @param $sPluginId
     *
     * @return mixed
     */
    public function GetPluginManifest($sPluginId) {

        $sXmlFile = $this->sPluginsCommonDir . $sPluginId . '/' . self::PLUGIN_XML_FILE;
        if ($sXml = F::File_GetContents($sXmlFile)) {
            return $sXml;
        }
    }

    /**
     * Получает список информации обо всех плагинах, загруженных в plugin-директорию
     *
     * @param   array   $aFilter
     * @param   bool    $bAsArray
     *
     * @return  array
     */
    public function GetList($aFilter = array(), $bAsArray = true) {

        if (is_null($this->aPluginsList)) {
            // Если списка плагинов нет, то создаем его
            if ($aPaths = glob($this->sPluginsCommonDir . '*', GLOB_ONLYDIR)) {
                $aList = array_map('basename', $aPaths);
                $aActivePlugins = $this->GetActivePlugins();
                foreach ($aList as $sPluginId) {
                    if ($bActive = in_array($sPluginId, $aActivePlugins)) {
                        $nNum = array_search($sPluginId, $aActivePlugins) + 1;
                    } else {
                        $nNum = -1;
                    }

                    // Создаем сущность плагина по его манифесту
                    $oPluginEntity = Engine::GetEntity('Plugin', $sPluginId);
                    if ($oPluginEntity->GetId()) {
                        // Если сущность плагина создана, то...
                        $oPluginEntity->SetNum($nNum);
                        $oPluginEntity->SetIsActive($bActive);
                        $this->aPluginsList[$sPluginId] = $oPluginEntity;
                    }
                }
            } else {
                $this->aPluginsList = array();
            }
        }

        // Формируем список на выдачу
        $aPlugins = array();
        if (isset($aFilter['active']) || $bAsArray) {
            foreach ($this->aPluginsList as $sPluginId => $oPluginEntity) {
                if (!isset($aFilter['active'])
                    || ($aFilter['active'] && $oPluginEntity->GetIsActive())
                    || (!$aFilter['active'] && !$oPluginEntity->GetIsActive())
                ) {

                    if ($bAsArray) {
                        $aPlugins[$sPluginId] = $oPluginEntity->_getData();
                    } else {
                        $aPlugins[$sPluginId] = $oPluginEntity;
                    }
                }
            }
        } else {
            $aPlugins = $this->aPluginsList;
        }
        // Если нужно, то сортируем плагины
        if ($aPlugins && isset($aFilter['order'])) {
            if ($aFilter['order'] == 'name') {
                uasort($aPlugins, array($this, '_PluginCompareByName'));
            } elseif ($aFilter['order'] == 'priority') {
                uasort($aPlugins, array($this, '_PluginCompareByPriority'));
            }
        }
        return $aPlugins;
    }

    /**
     * Возвращает список плагинов
     *
     * @param   bool|null   - $bActive
     *
     * @return  array
     */
    public function GetPluginsList($bActive = null) {

        $aFilter = array('order' => 'priority');
        if (!is_null($bActive)) {
            $aFilter['active'] = (bool)$bActive;
        }
        $aPlugins = $this->GetList($aFilter, false);
        return $aPlugins;
    }

    public function _PluginCompareByName($aPlugin1, $aPlugin2) {

        if ((string)$aPlugin1['property']->name->data == (string)$aPlugin2['property']->name->data) {
            return 0;
        }
        return ((string)$aPlugin1['property']->name->data < (string)$aPlugin2['property']->name->data) ? -1 : 1;
    }

    public function _PluginCompareByPriority($aPlugin1, $aPlugin2) {

        if (is_object($aPlugin1)) {
            $aPlugin1 = $aPlugin1->_getData();
        }
        if (is_object($aPlugin2)) {
            $aPlugin2 = $aPlugin2->_getData();
        }
        if ($aPlugin1['priority'] == $aPlugin2['priority']) {
            if (($aPlugin1['num'] == $aPlugin2['num'])) {
                // оба плагина не активированы - сортировка по имени
                if ($aPlugin1['num'] == -1) {
                    if (($aPlugin1['id'] == $aPlugin2['id'])) {
                        return 0;
                    } else {
                        return ($aPlugin1['id'] < $aPlugin2['id']) ? -1 : 1;
                    }
                } else {
                    return 0;
                }
            } else {
                // неактивированные плагины идут ниже
                if ($aPlugin1['num'] == -1) {
                    return 1;
                } elseif ($aPlugin2['num'] == -1) {
                    return -1;
                }
                return ($aPlugin1['num'] < $aPlugin2['num']) ? -1 : 1;
            }
        }
        if (strtolower($aPlugin1['priority']) == 'top') {
            return -1;
        } elseif (strtolower($aPlugin2['priority']) == 'top') {
            return 1;
        }
        return (($aPlugin1['priority'] > $aPlugin2['priority']) ? -1 : 1);
    }

    /**
     * Активация плагина
     *
     * @param   string  $sPluginId  - код плагина
     *
     * @return  bool
     */
    public function Activate($sPluginId) {

        $aConditions = array(
            '<'  => 'lt', 'lt' => 'lt',
            '<=' => 'le', 'le' => 'le',
            '>'  => 'gt', 'gt' => 'gt',
            '>=' => 'ge', 'ge' => 'ge',
            '==' => 'eq', '=' => 'eq', 'eq' => 'eq',
            '!=' => 'ne', '<>' => 'ne', 'ne' => 'ne'
        );

        // получаем список неактивированных плагинов
        $aPlugins = $this->GetPluginsList(false);
        if (!isset($aPlugins[$sPluginId])) {
            return false;
        }

        $sPluginName = F::StrCamelize($sPluginId);

        $sFile = F::File_NormPath("{$this->sPluginsCommonDir}{$sPluginId}/Plugin{$sPluginName}.class.php");
        if (F::File_Exists($sFile)) {
            F::IncludeFile($sFile);

            $sClassName = "Plugin{$sPluginName}";
            $oPlugin = new $sClassName;
            $oPluginEntity = $oPlugin->GetPluginEntity();

            // Проверяем совместимость с версией Alto
            if (!$oPluginEntity->EngineCompatible()) {
                $this->Message_AddError(
                    $this->Lang_Get(
                        'action.admin.plugin_activation_version_error',
                        array(
                             'version' => $oPluginEntity->RequiredAltoVersion(),
                        )
                    ),
                    $this->Lang_Get('error'),
                    true
                );
                return false;
            }

            // * Проверяем системные требования
            if ($oPluginEntity->RequiredPhpVersion()) {
                // Версия PHP
                if (!version_compare(PHP_VERSION, $oPluginEntity->RequiredPhpVersion(), '>=')
                ) {
                    $this->Message_AddError(
                        $this->Lang_Get(
                            'action.admin.plugin_activation_error_php',
                            array(
                                 'version' => $oPluginEntity->RequiredPhpVersion(),
                            )
                        ),
                        $this->Lang_Get('error'),
                        true
                    );
                    return false;
                }
            }

            // * Проверяем наличие require-плагинов
            if ($aRequiredPlugins = $oPluginEntity->RequiredPlugins()) {
                $aActivePlugins = $this->GetActivePlugins();
                $iError = 0;
                foreach ($aRequiredPlugins as $sReqPlugin) {

                    // * Есть ли требуемый активный плагин
                    if (!in_array($sReqPlugin, $aActivePlugins)) {
                        $iError++;
                        $this->Message_AddError(
                            $this->Lang_Get(
                                'action.admin.plugins_activation_requires_error',
                                array(
                                     'plugin' => ucfirst($sReqPlugin),
                                )
                            ),
                            $this->Lang_Get('error'),
                            true
                        );
                    } // * Проверка требуемой версии, если нужно
                    else {
                        if (isset($sReqPlugin['name'])) {
                            $sReqPluginName = (string)$sReqPlugin['name'];
                        }
                        else {
                            $sReqPluginName = ucfirst($sReqPlugin);
                        }

                        if (isset($sReqPlugin['version'])) {
                            $sReqVersion = $sReqPlugin['version'];
                            if (isset($sReqPlugin['condition'])
                                && array_key_exists(
                                    (string)$sReqPlugin['condition'], $aConditions
                                )
                            ) {
                                $sReqCondition = $aConditions[(string)$sReqPlugin['condition']];
                            } else {
                                $sReqCondition = 'eq';
                            }
                            $sClassName = "Plugin{$sReqPlugin}";
                            $oReqPlugin = new $sClassName;

                            // Получаем версию требуемого плагина
                            $sReqPluginVersion = $oReqPlugin->GetVersion();

                            if (!$sReqPluginVersion) {
                                $iError++;
                                $this->Message_AddError(
                                    $this->Lang_Get(
                                        'action.admin.plugin_havenot_getversion_method',
                                        array('plugin' => $sReqPluginName)
                                    ),
                                    $this->Lang_Get('error'),
                                    true
                                );
                            } else {
                                // * Если требуемый плагин возвращает версию, то проверяем ее
                                if (!version_compare($sReqPluginVersion, $sReqVersion, $sReqCondition)) {
                                    $sTextKey = 'action.admin.plugin_activation_reqversion_error_' . $sReqCondition;
                                    $iError++;
                                    $this->Message_AddError(
                                        $this->Lang_Get(
                                            $sTextKey,
                                            array(
                                                 'plugin'  => $sReqPluginName,
                                                 'version' => $sReqVersion
                                            )
                                        ),
                                        $this->Lang_Get('error'),
                                        true
                                    );
                                }
                            }
                        }
                    }
                }
                if ($iError) {
                    return false;
                }
            }

            // * Проверяем, не вступает ли данный плагин в конфликт с уже активированными
            // * (по поводу объявленных делегатов)
            $aPluginDelegates = $oPlugin->GetDelegates();
            $iError = 0;
            foreach ($this->aDelegates as $sGroup => $aReplaceList) {
                $iCount = 0;
                if (isset($aPluginDelegates[$sGroup])
                    && is_array($aPluginDelegates[$sGroup])
                    && $iCount = sizeof($aOverlap = array_intersect_key($aReplaceList, $aPluginDelegates[$sGroup]))
                ) {
                    $iError += $iCount;
                    foreach ($aOverlap as $sResource => $aConflict) {
                        $this->Message_AddError(
                            $this->Lang_Get(
                                'plugins_activation_overlap', array(
                                                                   'resource' => $sResource,
                                                                   'delegate' => $aConflict['delegate'],
                                                                   'plugin'   => $aConflict['sign']
                                                              )
                            ),
                            $this->Lang_Get('error'), true
                        );
                    }
                }
                if ($iCount) {
                    return false;
                }
            }
            $bResult = $oPlugin->Activate();
        } else {
            // * Исполняемый файл плагина не найден
            $this->Message_AddError(
                $this->Lang_Get('action.admin.plugin_file_not_found', array('file' => $sFile)),
                $this->Lang_Get('error'),
                true
            );
            return false;
        }

        if ($bResult) {
            // Надо обязательно очистить кеш здесь
            $this->Cache_Clean();
            $this->Viewer_ClearAll();

            // Переопределяем список активированных пользователем плагинов
            if (!$this->_addActivePlugins($oPluginEntity)) {
                $this->Message_AddError(
                    $this->Lang_Get('action.admin.plugin_write_error', array('file' => $this->sPluginsDatFile)),
                    $this->Lang_Get('error'), true
                );
                $bResult = false;
            }
        }
        return $bResult;

    } // function Activate(...)

    protected function _addActivePlugins($oPluginEntity) {

        $aPluginsList = $this->GetPluginsList(true);
        $aPluginsList[$oPluginEntity->GetId()] = $oPluginEntity;
        if (sizeof($aPluginsList)) {
            uasort($aPluginsList, array($this, '_PluginCompareByPriority'));
        }
        $this->SetActivePlugins(array_keys($aPluginsList));
        return $aPluginsList;
    }

    /**
     * Деактивация
     *
     * @param   string  $sPluginId  - код плагина
     *
     * @return  null|bool
     */
    public function Deactivate($sPluginId) {

        // получаем список активированных плагинов
        $aPlugins = $this->GetPluginsList(true);
        if (!isset($aPlugins[$sPluginId])) {
            return null;
        }

        $sPluginName = F::StrCamelize($sPluginId);

        $sFile = "{$this->sPluginsCommonDir}{$sPluginId}/Plugin{$sPluginName}.class.php";
        if (F::File_Exists($sFile)) {
            F::IncludeFile($sFile);

            $sClassName = "Plugin{$sPluginName}";
            $oPlugin = new $sClassName;

            /**
             * TODO: Проверять зависимые плагины перед деактивацией
             */
            $bResult = $oPlugin->Deactivate();
        } else {
            // Исполняемый файл плагина не найден
            $this->Message_AddError(
                $this->Lang_Get('plugins_activation_file_not_found'),
                $this->Lang_Get('error'),
                true
            );
            return false;
        }

        if ($bResult) {
            // * Переопределяем список активированных пользователем плагинов
            $aActivePlugins = $this->GetActivePlugins();

            // * Вносим данные в файл о деактивации плагина
            $aIndex = array_keys($aActivePlugins, $sPluginId);
            if (is_array($aIndex)) {
                unset($aActivePlugins[array_shift($aIndex)]);
            }

            // * Сбрасываем весь кеш, т.к. могут быть закешированы унаследованые плагинами сущности
            $this->Cache_Clean();
            if (!$this->SetActivePlugins($aActivePlugins)) {
                $this->Message_AddError(
                    $this->Lang_Get('action.admin.plugin_activation_file_write_error'),
                    $this->Lang_Get('error'),
                    true
                );
                return;
            }

            // * Очищаем компилированные шаблоны Smarty
            $this->Viewer_ClearSmartyFiles();
        }
        return $bResult;
    }

    /**
     * Возвращает список активированных плагинов в системе
     *
     * @return array
     */
    public function GetActivePlugins() {

        return F::GetPluginsList();
    }

    /**
     * Активирован ли указанный плагин
     *
     * @param $sPlugin
     *
     * @return bool
     */
    public function IsActivePlugin($sPlugin) {

        $aPlugins = $this->GetActivePlugins();
        return in_array($sPlugin, $aPlugins);
    }

    /**
     * Записывает список активных плагинов в файл PLUGINS.DAT
     *
     * @param array|string $aPlugins    Список плагинов
     *
     * @return bool
     */
    public function SetActivePlugins($aPlugins) {

        if (!is_array($aPlugins)) {
            $aPlugins = array($aPlugins);
        }
        $aPlugins = array_unique(array_map('trim', $aPlugins));

        // * Записываем данные в файл PLUGINS.DAT
        if (F::File_PutContents(
            $this->sPluginsAppDir . Config::Get('sys.plugins.activation_file'), implode(PHP_EOL, $aPlugins)
        ) !== false
        ) {
            return true;
        }
        return false;
    }

    /**
     * Удаляет плагины с сервера
     *
     * @param array $aPlugins    Список плагинов для удаления
     */
    public function Delete($aPlugins) {

        if (!is_array($aPlugins)) {
            $aPlugins = array($aPlugins);
        }

        $aActivePlugins = $this->GetActivePlugins();
        foreach ($aPlugins as $sPluginId) {
            if (!is_string($sPluginId)) {
                continue;
            }

            // * Если плагин активен, деактивируем его
            if (in_array($sPluginId, $aActivePlugins)) {
                $this->Deactivate($sPluginId);
            }

            // * Удаляем директорию с плагином
            F::File_RemoveDir($this->sPluginsCommonDir . $sPluginId);
        }
    }

    /**
     * Перенаправление вызовов на модули, экшены, сущности
     *
     * @param  string $sType
     * @param  string $sFrom
     * @param  string $sTo
     * @param  string $sSign
     */
    public function Delegate($sType, $sFrom, $sTo, $sSign = __CLASS__) {

        // * Запрещаем неподписанные делегаты
        if (!is_string($sSign) || !strlen($sSign)) {
            return;
        }
        if (!in_array($sType, array_keys($this->aDelegates)) || !$sFrom || !$sTo) {
            return;
        }

        $this->aDelegates[$sType][trim($sFrom)] = array(
            'delegate' => trim($sTo),
            'sign'     => $sSign
        );
    }

    /**
     * Добавляет в стек наследника класса
     *
     * @param string $sFrom
     * @param string $sTo
     * @param string $sSign
     */
    public function Inherit($sFrom, $sTo, $sSign = __CLASS__) {

        if (!is_string($sSign) || !strlen($sSign)) {
            return;
        }
        if (!$sFrom || !$sTo) {
            return;
        }

        $this->aInherits[trim($sFrom)]['items'][] = array(
            'inherit' => trim($sTo),
            'sign'    => $sSign
        );
        $this->aInherits[trim($sFrom)]['position'] = count($this->aInherits[trim($sFrom)]['items']) - 1;
    }

    /**
     * Получает следующего родителя у наследника.
     * ВНИМАНИЕ! Данный метод нужно вызвать только из __autoload()
     *
     * @param string $sFrom
     *
     * @return string
     */
    public function GetParentInherit($sFrom) {

        if (!isset($this->aInherits[$sFrom]['items']) || count($this->aInherits[$sFrom]['items']) <= 1
            || $this->aInherits[$sFrom]['position'] < 1
        ) {
            return $sFrom;
        }
        $this->aInherits[$sFrom]['position']--;
        return $this->aInherits[$sFrom]['items'][$this->aInherits[$sFrom]['position']]['inherit'];
    }

    /**
     * Возвращает список наследуемых классов
     *
     * @param string $sFrom
     *
     * @return null|array
     */
    public function GetInherits($sFrom) {

        if (isset($this->aInherits[trim($sFrom)])) {
            return $this->aInherits[trim($sFrom)]['items'];
        }
        return null;
    }

    /**
     * Возвращает последнего наследника в цепочке
     *
     * @param $sFrom
     *
     * @return null|string
     */
    public function GetLastInherit($sFrom) {

        if (isset($this->aInherits[trim($sFrom)])) {
            return $this->aInherits[trim($sFrom)]['items'][count($this->aInherits[trim($sFrom)]['items']) - 1];
        }
        return null;
    }

    /**
     * Возвращает делегат модуля, экшена, сущности.
     * Если делегат не определен, пытается найти наследника, иначе отдает переданный в качестве sender`a параметр
     *
     * @param  string $sType
     * @param  string $sFrom
     *
     * @return string
     */
    public function GetDelegate($sType, $sFrom) {

        if (isset($this->aDelegates[$sType][$sFrom]['delegate'])) {
            return $this->aDelegates[$sType][$sFrom]['delegate'];
        } elseif ($aInherit = $this->GetLastInherit($sFrom)) {
            return $aInherit['inherit'];
        }
        return $sFrom;
    }

    /**
     * @param string $sType
     * @param string $sFrom
     *
     * @return array|null
     */
    public function GetDelegates($sType, $sFrom) {

        if (isset($this->aDelegates[$sType][$sFrom]['delegate'])) {
            return array($this->aDelegates[$sType][$sFrom]['delegate']);
        } else {
            if ($aInherits = $this->GetInherits($sFrom)) {
                $aReturn = array();
                foreach (array_reverse($aInherits) as $v) {
                    $aReturn[] = $v['inherit'];
                }
                return $aReturn;
            }
        }
        return null;
    }

    /**
     * Возвращает цепочку делегатов
     *
     * @param string $sType
     * @param string $sTo
     *
     * @return array
     */
    public function GetDelegationChain($sType, $sTo) {

        $sRootDelegater = $this->GetRootDelegater($sType, $sTo);
        return $this->collectAllDelegatesRecursive($sType, array($sRootDelegater));
    }

    /**
     * Возвращает делегируемый класс
     *
     * @param string $sType
     * @param string $sTo
     *
     * @return string
     */
    public function GetRootDelegater($sType, $sTo) {

        $sItem = $sTo;
        $sItemDelegater = $this->GetDelegater($sType, $sTo);
        while (empty($sRootDelegater)) {
            if ($sItem == $sItemDelegater) {
                $sRootDelegater = $sItem;
            }
            $sItem = $sItemDelegater;
            $sItemDelegater = $this->GetDelegater($sType, $sItemDelegater);
        }
        return $sRootDelegater;
    }

    /**
     * Составляет цепочку делегатов
     *
     * @param string $sType
     * @param string $aDelegates
     *
     * @return array
     */
    public function collectAllDelegatesRecursive($sType, $aDelegates) {

        foreach ($aDelegates as $sClass) {
            if ($aNewDelegates = $this->GetDelegates($sType, $sClass)) {
                $aDelegates = array_merge($this->collectAllDelegatesRecursive($sType, $aNewDelegates), $aDelegates);
            }
        }
        return $aDelegates;
    }

    /**
     * Возвращает делегирующий объект по имени делегата
     *
     * @param  string $sType Объект
     * @param  string $sTo   Делегат
     *
     * @return string
     */
    public function GetDelegater($sType, $sTo) {

        $aDelegateMapper = array();
        foreach ($this->aDelegates[$sType] as $kk => $vv) {
            if ($vv['delegate'] == $sTo) {
                $aDelegateMapper[$kk] = $vv;
            }
        }
        if (is_array($aDelegateMapper) && count($aDelegateMapper)) {
            $aKeys = array_keys($aDelegateMapper);
            return array_shift($aKeys);
        }
        foreach ($this->aInherits as $k => $v) {
            $aInheritMapper = array();
            foreach ($v['items'] as $kk => $vv) {
                if ($vv['inherit'] == $sTo) {
                    $aInheritMapper[$kk] = $vv;
                }
            }
            if (is_array($aInheritMapper) && count($aInheritMapper)) {
                return $k;
            }
        }
        return $sTo;
    }

    /**
     * Возвращает подпись делегата модуля, экшена, сущности.
     *
     * @param  string $sType
     * @param  string $sFrom
     *
     * @return string|null
     */
    public function GetDelegateSign($sType, $sFrom) {

        if (isset($this->aDelegates[$sType][$sFrom]['sign'])) {
            return $this->aDelegates[$sType][$sFrom]['sign'];
        }
        if ($aInherit = $this->GetLastInherit($sFrom)) {
            return $aInherit['sign'];
        }
        return null;
    }

    /**
     * Возвращает true, если установлено правило делегирования
     * и класс является базовым в данном правиле
     *
     * @param  string $sType
     * @param  string $sFrom
     *
     * @return bool
     */
    public function isDelegater($sType, $sFrom) {

        if (isset($this->aDelegates[$sType][$sFrom]['delegate'])) {
            return true;
        } elseif ($aInherit = $this->GetLastInherit($sFrom)) {
            return true;
        }
        return false;
    }

    /**
     * Возвращает true, если устано
     *
     * @param  string $sType
     * @param  string $sTo
     *
     * @return bool
     */
    public function isDelegated($sType, $sTo) {
        /**
         * Фильтруем маппер делегатов/наследников
         * @var array
         */
        $aDelegateMapper = array();
        foreach ($this->aDelegates[$sType] as $kk => $vv) {
            if ($vv['delegate'] == $sTo) {
                $aDelegateMapper[$kk] = $vv;
            }
        }
        if (is_array($aDelegateMapper) && count($aDelegateMapper)) {
            return true;
        }
        foreach ($this->aInherits as $k => $v) {
            $aInheritMapper = array();
            foreach ($v['items'] as $kk => $vv) {
                if ($vv['inherit'] == $sTo) {
                    $aInheritMapper[$kk] = $vv;
                }
            }
            if (is_array($aInheritMapper) && count($aInheritMapper)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Возвращает список объектов, доступных для делегирования
     *
     * @return array
     */
    public function GetDelegateObjectList() {

        return array_keys($this->aDelegates);
    }

    /**
     * Рекурсивно ищет манифест плагина в подпапках
     *
     * @param   string  $sDir
     *
     * @return  string|null
     */
    protected function _seekManifest($sDir) {

        if ($aFiles = glob($sDir . self::PLUGIN_XML_FILE)) {
            return array_shift($aFiles);
        } else {
            $aSubDirs = glob($sDir . '*', GLOB_ONLYDIR);
            foreach ($aSubDirs as $sSubDir) {
                if ($sFile = $this->_seekManifest($sSubDir . '/')) {
                    return $sFile;
                }
            }
        }
        return false;
    }

    /**
     * Распаковывает архив с плагином и перемещает его в нужную папку
     *
     * @param $sPackFile
     *
     * @return  bool
     */
    public function UnpackPlugin($sPackFile) {

        $zip = new ZipArchive;
        if ($zip->open($sPackFile) === true) {
            $sUnpackDir = F::File_NormPath(dirname($sPackFile) . '/_unpack/');
            if (!$zip->extractTo($sUnpackDir)) {
                $this->Message_AddError($this->Lang_Get('action.admin.err_extract_zip_file'), $this->Lang_Get('error'));
                return false;
            } else {
                // Ищем в папках XML-манифест
                $aDirs = glob($sUnpackDir . '*', GLOB_ONLYDIR);
                $sXmlFile = '';
                if ($aDirs) {
                    foreach ($aDirs as $sDir) {
                        if ($sXmlFile = $this->_seekManifest($sDir . '/')) {
                            break;
                        }
                    }
                }
                if (!$sXmlFile) {
                    $this->Message_AddError(
                        $this->Lang_Get('action.admin.file_not_found', array('file' => self::PLUGIN_XML_FILE)),
                        $this->Lang_Get('error')
                    );
                    return false;
                }
                $sPluginSrc = dirname($sXmlFile);
                $sPluginDir = basename($sPluginSrc);
                $sPluginPath = $this->GetPluginsDir() . '/' . $sPluginDir . '/';
                if (F::File_CopyDir($sPluginSrc, $sPluginPath)) {
                    $this->Message_AddNotice($this->Lang_Get('action.admin.plugin_added_ok'));
                } else {
                    $this->Message_AddError($this->Lang_Get('action.admin.plugin_added_err'), $this->Lang_Get('error'));
                }
            }
            $zip->close();
        } else {
            $this->Message_AddError($this->Lang_Get('action.admin.err_open_zip_file'), $this->Lang_Get('error'));
        }
    }
}

// EOF