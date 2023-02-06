<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;

Loc::loadMessages(__FILE__);

class alexsoft extends CModule
{
    private $mainCodeIblock = 'alexsoft';

    public function __construct()
    {
        if (is_file(__DIR__.'/version.php')) {
            include_once(__DIR__.'/version.php');
            $this->MODULE_ID = get_class($this);
            $this->MODULE_VERSION = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
            $this->MODULE_NAME = Loc::getMessage('NAME');
            $this->MODULE_DESCRIPTION = Loc::getMessage('DESCRIPTION');
        } else {
            CAdminMessage::showMessage(
                Loc::getMessage('FILE_NOT_FOUND').' version.php'
            );
        }
    }

    public function doInstall()
    {
        global $APPLICATION;

        // мы используем функционал нового ядра D7 — поддерживает ли его система?
        if (CheckVersion(ModuleManager::getVersion('main'), '14.00.00')) {
            //            // копируем файлы, необходимые для работы модуля
            //            $this->installFiles();
            //            // создаем таблицы БД, необходимые для работы модуля
            //            $this->installDB();
            // регистрируем модуль в системе
            ModuleManager::registerModule($this->MODULE_ID);

            //Создаем AlexSoft
            $this->AddIblockType();
            $arIblockIDs = $this->AddIblock();
        } else {
            CAdminMessage::showMessage(
                Loc::getMessage('INSTALL_ERROR')
            );

            return;
        }

        $APPLICATION->includeAdminFile(
            Loc::getMessage('SINSTALL_TITLE').' «'.Loc::getMessage('NAME').'»',
            __DIR__.'/step.php'
        );
    }

    //    public function installFiles()
    //    {
    //        // копируем js-файлы, необходимые для работы модуля
    //        CopyDirFiles(
    //            __DIR__.'/assets/scripts',
    //            Application::getDocumentRoot().'/bitrix/js/'.$this->MODULE_ID.'/',
    //            true,
    //            true
    //        );
    //        // копируем css-файлы, необходимые для работы модуля
    //        CopyDirFiles(
    //            __DIR__.'/assets/styles',
    //            Application::getDocumentRoot().'/bitrix/css/'.$this->MODULE_ID.'/',
    //            true,
    //            true
    //        );
    //    }
    //
    //    public function installDB()
    //    {
    //        return;
    //    }


    // Создание инфоблоков
    public function AddIblockType()
    {
        global $DB;
        CModule::IncludeModule('iblock');

        // смотрим чтобы такого инфоблока не было
        $db_iblock_type = CIBlockType::GetList(
            ["SORT" => "ASC"],
            ["ID" => $this->mainCodeIblock]
        );
        // если его нет - создаём
        if ( ! $ar_iblock_type = $db_iblock_type->Fetch()) {
            $obBlocktype = new CIBlockType;
            $DB->StartTransaction();

            // массив полей для нового типа инфоблоков
            $arIBType = [
                'ID'       => $this->mainCodeIblock,
                'SECTIONS' => 'Y',
                'IN_RSS'   => 'N',
                'SORT'     => 500,
                'LANG'     => [
                    'en' => [
                        'NAME' => GetMessage("IBLOCK_ALEXSOFT_TYPE_EN"),
                    ],
                    'ru' => [
                        'NAME' => GetMessage("IBLOCK_ALEXSOFT_TYPE_RU"),
                    ],
                ],
            ];

            // создаём новый тип для инфоблоков
            $resIBT = $obBlocktype->Add($arIBType);
            if ( ! $resIBT) {
                $DB->Rollback();
                echo 'Error: '.$obBlocktype->LAST_ERROR;
                die();
            } else {
                $DB->Commit();
            }
        } else {
            return false;
        }

        return $resIBT;
    }

    // Функция для создания инфоблока
    public function AddIblock()
    {
        CModule::IncludeModule("iblock");

        // Инфоблоки которые будем создавать
        $arIblockCodes = [
            'recall' => [
                "ACTIVE"         => "Y",
                "NAME"           => 'Отзывы',
                "CODE"           => 'recall',
                "IBLOCK_TYPE_ID" => $this->mainCodeIblock,
                "SITE_ID"        => "s1",
                "GROUP_ID"       => ["2" => "R"],
                "FIELDS"         => [
                    "CODE" => [
                        "IS_REQUIRED"   => "Y",
                        "DEFAULT_VALUE" => [
                            "TRANS_CASE"      => "L",
                            "UNIQUE"          => "Y",
                            "TRANSLITERATION" => "Y",
                            "TRANS_SPACE"     => "-",
                            "TRANS_OTHER"     => "-",
                        ],
                    ],
                ],
            ],
            'city'   => [
                "ACTIVE"         => "Y",
                "NAME"           => 'Города',
                "CODE"           => 'city',
                "IBLOCK_TYPE_ID" => $this->mainCodeIblock,
                "SITE_ID"        => "s1",
                "GROUP_ID"       => ["2" => "R"],
                "FIELDS"         => [
                    "CODE" => [
                        "IS_REQUIRED"   => "Y",
                        "DEFAULT_VALUE" => [
                            "TRANS_CASE"      => "L",
                            "UNIQUE"          => "Y",
                            "TRANSLITERATION" => "Y",
                            "TRANS_SPACE"     => "-",
                            "TRANS_OTHER"     => "-",
                        ],
                    ],
                ],
            ],
        ];

        $ib = new CIBlock;
        // Проверка на уникальность
        $resIBE = CIBlock::GetList(
            [],
            [
                'TYPE' => $this->mainCodeIblock,
                "CODE" => array_keys($arIblockCodes),
            ]
        );
        if ($ar_resIBE = $resIBE->Fetch()) {
            return false;
        } else {
            $arIDsIblock = [];
            foreach ($arIblockCodes as $key => $ar_iblock) {
                $arIDsIblock[] = $idInfoblock = $ib->Add($ar_iblock);
                if ('recall' == $key) {
                    $this->AddPropsRecall($idInfoblock);
                }
            }

            return $arIDsIblock;
        }
    }

    // Добавление свойств для инфоблока отзывы
    public function AddPropsRecall($IBLOCK_ID)
    {
        CModule::IncludeModule("iblock");

        // Массив полей для нового свойства
        $arFieldsProp = [
            [
                "NAME"          => GetMessage("RATING_PROP"),
                "ACTIVE"        => "Y",
                "SORT"          => "100",
                "CODE"          => "RATING",
                "PROPERTY_TYPE" => "N",
                "IBLOCK_ID"     => $IBLOCK_ID,
                "IS_REQUIRED"   => 'Y',
            ],
            [
                "NAME"          => GetMessage("CITY_PROP"),
                "ACTIVE"        => "Y",
                "SORT"          => "100",
                "CODE"          => "CITY",
                "PROPERTY_TYPE" => "G",
                "IBLOCK_ID"     => $IBLOCK_ID,
                "IS_REQUIRED"   => 'Y',
            ],
        ];

        $ibp = new CIBlockProperty;

        foreach ($arFieldsProp as $prop) {
            // Создаём свойство
            $propID = $ibp->Add($prop);
        }

        return true;
    }


    // Удаление
    public function DelIblocks()
    {
        global $DB;
        CModule::IncludeModule("iblock");
        $DB->StartTransaction();
        if ( ! CIBlockType::Delete($this->mainCodeIblock)) {
            $DB->Rollback();
            CAdminMessage::ShowMessage([
                "TYPE"    => "ERROR",
                "MESSAGE" => GetMessage("VTEST_IBLOCK_TYPE_DELETE_ERROR"),
                "DETAILS" => "",
                "HTML"    => true,
            ]);
        }
        $DB->Commit();
    }


    public function doUninstall()
    {
        global $APPLICATION;

        //        $this->uninstallFiles();
        //        $this->uninstallDB();

        //Чистим от инфоблока
        $this->DelIblocks();

        ModuleManager::unRegisterModule($this->MODULE_ID);

        $APPLICATION->includeAdminFile(
            Loc::getMessage('UNINSTALL_TITLE').' «'.Loc::getMessage('NAME').'»',
            __DIR__.'/unstep.php'
        );
    }

    //    public function uninstallFiles()
    //    {
    //        // удаляем js-файлы
    //        Directory::deleteDirectory(
    //            Application::getDocumentRoot().'/bitrix/js/'.$this->MODULE_ID
    //        );
    //        // удаляем css-файлы
    //        Directory::deleteDirectory(
    //            Application::getDocumentRoot().'/bitrix/css/'.$this->MODULE_ID
    //        );
    //        // удаляем настройки нашего модуля
    //        Option::delete($this->MODULE_ID);
    //    }
    //
    //    public function uninstallDB()
    //    {
    //        return;
    //    }


}