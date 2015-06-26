<?php

namespace Craft;

class WordpressImportService extends BaseApplicationComponent {
    public function import() {
        // CONFIG
        $max = 0; // Set max to > 0 to limit amount of posts to import, usefull when testing.
        $xml_abs_path = CRAFT_BASE_PATH . 'storage/wp-export.xml'; // Path to export file
        $importFeaturedImage = true; // Set to false to disable featured images.
        $assetFolderId = 1; // Asset folder id.
        $wp_uploads_abs_path = CRAFT_BASE_PATH . 'storage/wp_images/'; // Path to Wordpress images, used when importing featured image.
        $tagGroupId = 1; // Group id for tags. Set to null to skip importing tags.
        $categoryGroupId = 1; // Group id for category. Set to null to skip importing categories.
        $sectionId = 2; // Section id.
        $typeId = 2; // Entry type id.
        $contentFields = array(
            'bodyFieldsHandle' => 'body',
            'tagsFieldsHandle' => 'tags',
            'featuredImageFieldsHandle' => 'blogImage',
            'categoriesFieldsHandle' => 'categories',
        );
        // END CONFIG

        $retVal = true;
        $i = 0;
        $xml = simplexml_load_file($xml_abs_path);
        foreach ($xml->channel[0]->item as $importEntry) {
            // Only import posts that are published
            $post_type = (string) $this->firstValue($importEntry->xpath('wp:post_type'));
            $status = (string) $this->firstValue($importEntry->xpath('wp:status'));
            if ($post_type != 'post' || $status != 'publish') {
                continue;
            }

            // Slug
            $slug = (string) $this->firstValue($importEntry->xpath('wp:post_name'));

            // Find existing entry to handle multiple imports gracefully.
            $criteria = craft()->elements->getCriteria(ElementType::Entry);
            $criteria->slug = $slug;
            $criteria->sectionId = $sectionId;
            if (!$entry = $criteria->first()) {
                $entry = new EntryModel();
            }
            $isNewEntry = !$entry->getAttribute('id');

            // Body
            $body = (string) $this->firstValue($importEntry->xpath('content:encoded'));
            // Clean up body
            // $body = trim($body, '&nbsp;');
            // $body = trim($body, "\n");

            // Title.
            $title = (string) $importEntry->title;
            // Clean up title
            //$title = mb_strtoupper(mb_substr($title, 0, 1)) . mb_substr(mb_strtolower($title), 1);

            // Featured image.
            $blogImage = array();
            if ($importFeaturedImage) {
                $thumbnail_id = (int) $this->firstValue($importEntry->xpath('wp:postmeta[wp:meta_key[text() = "_thumbnail_id"]]/wp:meta_value'));
                if ($thumbnail_id) {
                    $asset = $xml->channel[0]->xpath('item[wp:post_id[text() = ' . $thumbnail_id . ']]')[0];
                    $path = (string) $this->firstValue($asset->xpath('wp:postmeta[wp:meta_key[text() = "_wp_attached_file"]]/wp:meta_value'));
                    $fileName = basename($path);

                    if (!$isNewEntry) {
                        // Find existing asset if this entry has been been imported
                        // previously.
                        $criteria = craft()->elements->getCriteria(ElementType::Asset);
                        $criteria->filename = $fileName;
                        $criteria->folderId = $assetFolderId;
                        $blogImage = $criteria->ids();
                    }
                    // Create new asset if none was found.
                    if (empty($blogImage)) {
                        $response = craft()->assets->insertFileByLocalPath(
                            $wp_uploads_abs_path . $path,
                            $fileName,
                            $assetFolderId,
                            AssetConflictResolution::KeepBoth
                        );

                        if ($response->isSuccess()) {
                            $fileId = $response->getDataItem('fileId');
                            $blogImage = array($fileId);
                        }
                    }
                }
            }

            // Tags
            $tagArray = array();
            if ($tagGroupId) {
                $tags = $importEntry->xpath('category[@domain="post_tag"]');
                if (!empty($tags)) {
                    foreach ($tags as $tag) {
                        $tag = (string) $tag;
                        // Find existing tag
                        $criteria = craft()->elements->getCriteria(ElementType::Tag);
                        $criteria->title = $tag;
                        $criteria->groupId = $tagGroupId;

                        if (!$criteria->total()) {
                            // Create tag if one doesn't already exist
                            $newtag = new TagModel();
                            $newtag->getContent()->title = $tag;
                            $newtag->groupId = $tagGroupId;

                            // Save tag
                            if (craft()->tags->saveTag($newtag)) {
                                $tagArray[] = $newtag->id;
                            }
                        } else {
                            $tagArray = array_merge($tagArray, $criteria->ids());
                        }
                    }
                }
            }

            // Categories
            $categoriesArray = array();
            if ($categoryGroupId) {
                $categoriesArray = array();
                $categories = $importEntry->xpath('category[@domain="category"]');
                if (!empty($categories)) {
                    foreach ($categories as $category) {
                        $category = (string) $category;
                        // Find existing category
                        $criteria = craft()->elements->getCriteria(ElementType::Category);
                        $criteria->title = $category;
                        $criteria->groupId = $categoryGroupId;

                        if (!$criteria->total()) {
                            // Create category if one doesn't already exist
                            $newCategory = new CategoryModel();
                            $newCategory->getContent()->title = $category;
                            $newCategory->groupId = $categoryGroupId;

                            // Save category
                            if (craft()->categories->saveCategory($newCategory)) {
                                $categoriesArray[] = $newCategory->id;
                            }
                        } else {
                            $categoriesArray = array_merge($categoriesArray, $criteria->ids());
                        }
                    }
                }
            }

            $entry->sectionId = $sectionId;
            $entry->typeId = $typeId;
            $entry->authorId = 1; // 1 for Admin
            $entry->enabled = true;
            $entry->postDate = (string) $this->firstValue($importEntry->xpath('wp:post_date_gmt'));;
            $entry->slug = $slug;

            $entry->getContent()->setAttributes(array(
                'title' => $title,
                $contentFields['bodyFieldsHandle'] => $body,
                $contentFields['tagsFieldsHandle'] => $tagArray,
                $contentFields['featuredImageFieldsHandle'] => $blogImage,
                $contentFields['categoriesFieldsHandle'] => $categoriesArray,
            ));

            // Save entry.
            $status = craft()->entries->saveEntry($entry);

            if ($status) {
                if ($max && ($i++ == $max - 1)) {
                    break;
                }
            } else {
                $retVal = false;
                break;
            }

        }
        return $retVal;
    }

    private function firstValue($simlexml_element) {
        // reset() resturns the first element or false if the array is empty,
        // exactly what we want.
        return (string) reset($simlexml_element);
    }
}
