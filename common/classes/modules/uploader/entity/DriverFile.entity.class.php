<?php

class ModuleUploader_EntityDriverFile extends Entity {

    /**
     * Checks if file exists in storage
     *
     * @param string $sFile
     *
     * @return string|bool
     */
    public function Exists($sFile) {

        return F::File_Exists($sFile);
    }

    /**
     * Saves file in storage
     *
     * @param string $sFile
     * @param string $sDestination
     *
     * @return bool|string
     */
    public function Store($sFile, $sDestination = null) {

        if (!$sDestination) {
            $oUser = $this->User_GetUserCurrent();
            if ($oUser) {
                return false;
            }
            $sDirUpload = $this->GetUserFileDir($oUser->getId());
            $sDestination = $this->Uniqname($sDirUpload, strtolower(F::File_GetExtension($sFile)));
        }
        if ($sDestination) {
            $sMimeType = ModuleImg::MimeType($sFile);
            $bIsImage = (strpos($sMimeType, 'image/') === 0);
            $nUserId = E::UserId();
            $sUuid = str_pad($nUserId, 8, '0', STR_PAD_LEFT) . '-' . md5_file($sFile);
            if ($sStoredFile = $this->Uploader_Move($sFile, $sDestination, true)) {
                $oStoredItem = Engine::GetEntity(
                    'Uploader_Item',
                    array(
                         'storage'           => 'file',
                         'uuid'              => $sUuid,
                         'original_filename' => basename($sFile),
                         'url'               => $this->Dir2Url($sStoredFile),
                         'file'              => $sStoredFile,
                         'user_id'           => $nUserId,
                         'mime_type'         => $sMimeType,
                         'is_image'          => $bIsImage,
                    )
                );
                return $oStoredItem;
            }
        }
    }

    /**
     * Deletes file from storage
     *
     * @param string $sFile
     *
     * @return bool
     */
    public function Delete($sFile) {

        if (strpos($sFile, '*')) {
            $bResult = F::File_DeleteAs($sFile);
        } else {
            $bResult = F::File_Delete($sFile);
        }
        if ($bResult) {
            // if folder is empty then remove it
            if (!F::File_ReadDir($sDir = dirname($sFile))) {
                F::File_RemoveDir($sDir);
            }
        }
        return $bResult;
    }

    /**
     * @param string $sFilePath
     *
     * @return string
     */
    public function Dir2Url($sFilePath) {

        return F::File_Dir2Url($sFilePath);
    }

    /**
     * @param string $sUrl
     *
     * @return bool
     */
    public function Url2Dir($sUrl) {

        if (F::File_LocalUrl($sUrl)) {
            return F::File_Url2Dir($sUrl);
        }
    }


}

// EOF