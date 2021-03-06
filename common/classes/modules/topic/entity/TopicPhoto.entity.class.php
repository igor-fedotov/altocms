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
 * Объект сущности аттача
 *
 * @package modules.topic
 * @since   1.0
 */
class ModuleTopic_EntityTopicPhoto extends Entity {
    /**
     * Возвращает ID объекта
     *
     * @return int|null
     */
    public function getId() {

        return $this->getProp('id');
    }

    /**
     * Возвращает ID топика
     *
     * @return int|null
     */
    public function getTopicId() {

        return $this->getProp('topic_id');
    }

    /**
     * Возвращает ключ временного владельца
     *
     * @return string|null
     */
    public function getTargetTmp() {

        return $this->getProp('target_tmp');
    }

    /**
     * Возвращает описание фото
     *
     * @return string|null
     */
    public function getDescription() {

        return $this->getProp('description');
    }

    /**
     * Вовзращает полный веб путь до файла
     *
     * @return string|null
     */
    public function getPath() {

        return $this->getProp('path');
    }

    /**
     * Возвращает полный веб путь до фото определенного размера
     *
     * @param string|null $sSize    Размер фото, например, '100' или '150crop' или '150x100' или 'x100'
     *
     * @return null|string
     */
    public function getUrl($sSize = null) {

        if ($sUrl = $this->getPath()) {
            if ($sSize) {
                $sResizedUrl = $this->getProp('_size-' . $sSize . '-url');
                if ($sResizedUrl) {
                    return $sResizedUrl;
                }
                $aPathInfo = pathinfo($sUrl);

                if (E::ActivePlugin('ls')) {
                    // Включена совместимость с LS
                    $sResizedUrl = $aPathInfo['dirname'] . '/' . $aPathInfo['filename'] . '_' . $sSize . '.'
                        . $aPathInfo['extension'];
                    if (F::File_LocalUrl($sResizedUrl) && !F::File_Exists(F::File_Url2Dir($sResizedUrl))) {
                        $sResizedUrl = '';
                    }
                }

                if (!$sResizedUrl) {
                    $nPos = strpos($sSize, 'x');
                    if ($nPos === false) {
                        $nHeight = $nWidth = intval($sSize);
                    } elseif ($nPos === 0) {
                        $nWidth = 0;
                        $nHeight = intval(substr($sSize, 1));
                    } else {
                        $nWidth = intval(substr($sSize, 0, $nPos));
                        $nHeight = intval(substr($sSize, $nPos));
                    }
                    if ($nWidth || $nHeight) {
                        $sResizedUrl = $sUrl . '-' . ($nWidth ? $nWidth : '') . 'x' . $nHeight;;
                        if (strpos($sSize, 'fit')) {
                            $sResizedUrl .= '-fit';
                        } else if (strpos($sSize, 'crop')) {
                            $sResizedUrl .= '-crop';
                        } else if (strpos($sSize, 'pad')) {
                            $sResizedUrl .= '-pad';
                        }
                        $sResizedUrl .= '.' . $aPathInfo['extension'];
                        if (Config::Get('module.image.autoresize')) {
                            $sFile = $this->Uploader_Url2Dir($sResizedUrl);
                            $this->setProp('_size-' . $sSize . '-file', $sFile);
                            if (!F::File_Exists($sFile)) {
                                $this->Img_Duplicate($sFile);
                            }
                        }
                    }
                }
                if ($sResizedUrl) {
                    $sUrl = F::File_NormPath($sResizedUrl);
                }
                $this->setProp('_size-' . $sSize . '-url', $sUrl);
            }
        }
        return $sUrl;
    }

    public function getImgSize($sSize = null) {

        $aSize = $this->getProp('_size-' . $sSize . '-imgsize');
        if (!$aSize) {
            $sFile = $this->getProp('_size-' . $sSize . '-file');
            if (!$sFile) {
                $sUrl = $this->getUrl($sSize);
                $sFile = $this->Uploader_Url2Dir($sUrl);
                $this->setProp('_size-' . $sSize . '-file', $sFile);
            }
            if ($sFile && F::File_Exists($sFile)) {
                $aSize = getimagesize($sFile);
                $this->setProp('_size-' . $sSize . '-imgsize', $aSize);
            }
        }
        return $aSize;
    }

    public function getImgWidth($sSize = null) {

        $aSize = $this->getImgSize($sSize);
        if (isset($aSize[0])) {
            return $aSize[0];
        }
    }

    public function getImgHeight($sSize = null) {

        $aSize = $this->getImgSize($sSize);
        if (isset($aSize[1])) {
            return $aSize[1];
        }
    }

    public function getImgSizeAttr($sSize = null) {

        $aSize = $this->getImgSize($sSize);
        if (isset($aSize[3])) {
            return $aSize[3];
        }
    }

    public function getWebPath($sWidth = null) {

        return $this->getUrl($sWidth);
    }

    /**
     * Устанавливает ID топика
     *
     * @param int $iTopicId
     */
    public function setTopicId($iTopicId) {

        $this->setProp('topic_id', $iTopicId);
    }

    /**
     * Устанавливает ключ временного владельца
     *
     * @param string $sTargetTmp
     */
    public function setTargetTmp($sTargetTmp) {

        $this->setProp('target_tmp', $sTargetTmp);
    }

    /**
     * Устанавливает описание фото
     *
     * @param string $sDescription
     */
    public function setDescription($sDescription) {

        $this->setProp('description', $sDescription);
    }

    public function buildMresource() {

        $oMresource = Engine::GetEntity('Mresource_MresourceRel');
        $oMresource->SetLink(false);
        $oMresource->SetType(ModuleMresource::TYPE_IMAGE | ModuleMresource::TYPE_PHOTO);
        $oMresource->SetUrl($this->Mresource_NormalizeUrl($this->GetPath()));
        return $oMresource;
    }

}

// EOF
