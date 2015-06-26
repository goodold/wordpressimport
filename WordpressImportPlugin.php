<?php

namespace Craft;

class WordpressImportPlugin extends BasePlugin {
    public function getName() {
        return Craft::t('Wordpress Import');
    }

    public function getVersion() {
        return '0.1';
    }

    public function getDeveloper() {
        return 'Good Old';
    }

    public function getDeveloperUrl() {
        return 'http://goodold.se';
    }

    public function hasCpSection() {
        return true;
    }
}
