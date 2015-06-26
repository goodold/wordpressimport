<?php

namespace Craft;

class WordpressImportController extends BaseController {
    public function actionImport() {
        if (craft()->wordpressImport->import()) {
            craft()->userSession->setNotice(Craft::t('Entries imported.'));
        } else {
            craft()->userSession->setError(Craft::t("Failed importing entries."));
        }
        $this->redirect('wordpressimport');
    }
}
