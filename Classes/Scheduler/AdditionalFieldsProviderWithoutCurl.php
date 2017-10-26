<?php
namespace DmitryDulepov\DdGooglesitemap\Scheduler;

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\AdditionalFieldProviderInterface;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

class AdditionalFieldsProviderWithoutCurl implements AdditionalFieldProviderInterface
{
    public function getAdditionalFields(
        array &$taskInfo,
        $task,
        SchedulerModuleController $schedulerModule
    ) {
        /** @var \DmitryDulepov\DdGooglesitemap\Scheduler\Task $task */
        $additionalFields = array();
        $domainRecordId = null;

        if ($task) {
            $domainRecordId = $task->getDomainRecordId();
        } else {
            $task = GeneralUtility::makeInstance(TaskWithoutCurl::class);
        }
        $indexFilePath = $task->getIndexFilePath();

        $additionalFields['domainRecord_withoutCurl'] = array(
            'code' => '<select class="wide" type="text" name="tx_scheduler[domainRecord_withoutCurl]">' .
                $this->buildSelectItems($domainRecordId) . '</select>',
            'label' => 'LLL:EXT:dd_googlesitemap/locallang.xml:scheduler.domainRecordLabel',
            'cshKey' => '',
            'cshLabel' => ''
        );
        $additionalFields['indexFilePath_withoutCurl'] = array(
            'code' => '<input class="wide" type="text" name="tx_scheduler[indexFilePath_withoutCurl]" value="' .
                htmlspecialchars($indexFilePath) . '" />',
            'label' => 'LLL:EXT:dd_googlesitemap/locallang.xml:scheduler.indexFieldLabel',
            'cshKey' => '',
            'cshLabel' => ''
        );

        return $additionalFields;
    }

    /**
     * Validates the additional fields' values
     *
     * @param array $submittedData An array containing the data submitted by the add/edit task form
     * @param SchedulerModuleController $schedulerModule Reference to the scheduler backend module
     * @return boolean TRUE if validation was ok (or selected class is not relevant), FALSE otherwise
     */
    public function validateAdditionalFields(array &$submittedData, SchedulerModuleController $schedulerModule)
    {
        $errors = array();

        $this->validateDomainRecord($submittedData, $errors);
        $this->validateIndexFilePath($submittedData, $errors);

        foreach ($errors as $error) {
            /** @noinspection PhpUndefinedMethodInspection */
            $error = $GLOBALS['LANG']->sL('LLL:EXT:dd_googlesitemap/locallang.xml:' . $error);
            $this->addErrorMessage($error);
        }

        return count($errors) == 0;
    }

    /**
     * Takes care of saving the additional fields' values in the task's object
     *
     * @param array $submittedData An array containing the data submitted by the add/edit task form
     * @param AbstractTask $task Reference to the scheduler backend module
     * @return void
     */
    public function saveAdditionalFields(array $submittedData, AbstractTask $task)
    {
        /** @var \DmitryDulepov\DdGooglesitemap\Scheduler\Task $task */
        $task->setDomainRecordId($submittedData['domainRecord_withoutCurl']);
        $task->setIndexFilePath($submittedData['indexFilePath_withoutCurl']);
    }

    /**
     * Adds a error message as a flash message.
     *
     * @param string $message
     * @return void
     */
    protected function addErrorMessage($message)
    {
        $flashMessage = GeneralUtility::makeInstance(FlashMessage::class, $message, '', FlashMessage::ERROR);
        /** @var \TYPO3\CMS\Core\Messaging\FlashMessage $flashMessage */
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        /** @var \TYPO3\CMS\Core\Messaging\FlashMessageService $flashMessageService */
        $flashMessageService->getMessageQueueByIdentifier()->enqueue($flashMessage);
    }

    /**
     * Validates the domain record.
     *
     * @param array $submittedData
     * @param array $errors
     */
    protected function validateDomainRecord(array &$submittedData, array &$errors)
    {
        if (array_key_exists('domainRecord_withoutCurl', $submittedData)) {
            $submittedData['domainRecord_withoutCurl'] = intval($submittedData['domainRecord_withoutCurl']);
            if ($submittedData['domainRecord_withoutCurl'] <= 0) {
                $errors[] = 'scheduler.error.missingHost';
            } else {
                $sysDomainRow = $GLOBALS['TYPO3_DB']
                    ->exec_SELECTgetSingleRow('uid', 'sys_domain', 'uid =' . $submittedData['domainRecord_withoutCurl']);
                if (!is_array($sysDomainRow)) {
                    $errors[] = 'scheduler.error.missingHost';
                }
            }
        }
    }

    /**
     * Validates index file path.
     *
     * @param array $submittedData
     * @param array $errors
     * @return void
     */
    protected function validateIndexFilePath(array &$submittedData, array &$errors)
    {
        if (array_key_exists('indexFilePath_withoutCurl', $submittedData)) {
            if (GeneralUtility::isAbsPath($submittedData['indexFilePath_withoutCurl'])) {
                $errors[] = 'scheduler.error.badIndexFilePath';
            } else {
                $testPath = GeneralUtility::getFileAbsFileName($submittedData['indexFilePath_withoutCurl'], true);
                if (!file_exists($testPath)) {
                    if (!@touch($testPath)) {
                        $errors[] = 'scheduler.error.badIndexFilePath';
                    } else {
                        unlink($testPath);
                    }
                }
            }
        }
    }

    /**
     * Generates a selectbox for domain records
     *
     * @param integer $selectedRecord
     * @return string
     */
    protected function buildSelectItems($selectedRecord)
    {
        $availableDomainRecords = $GLOBALS['TYPO3_DB']
            ->exec_SELECTgetRows('uid,domainName', 'sys_domain', '1=1', '', 'domainName ASC');

        $list = '<option value=""></option>';

        if (is_array($availableDomainRecords)) {
            foreach ($availableDomainRecords as $record) {
                $selected = ($record['uid'] == $selectedRecord) ? ' selected="selected"' : '';
                $list .= '<option value="' . $record['uid'] . '"' . $selected . '>' .
                    htmlspecialchars($record['domainName']) . '</option>';
            }
        }
        return $list;
    }
}
