<?php
namespace DmitryDulepov\DdGooglesitemap\Scheduler;

use DmitryDulepov\DdGooglesitemap\Generator\EntryPoint;
use TYPO3\CMS\Core\TimeTracker\NullTimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

class TaskWithoutCurl extends AbstractTask
{
    const DEFAULT_FILE_PATH = 'typo3temp/dd_googlesitemap';

    /** @var string */
    protected $indexFilePath;

    /** @var integer */
    protected $domainRecordId;

    /**
     * Creates the instance of the class. This call initializes the index file
     * path to the random value. After the task is configured, the user may
     * change the file and the file name will be serialized with the task and
     * used later.
     *
     * @see __sleep
     */
    public function __construct() {
        parent::__construct();
        $this->indexFilePath = self::DEFAULT_FILE_PATH . '/' . GeneralUtility::getRandomHexString(24) . '.xml';
    }

    /**
     * @param string $indexFilePath
     */
    public function setIndexFilePath($indexFilePath)
    {
        $this->indexFilePath = $indexFilePath;
    }

    /**
     * @param int $domainRecordId
     */
    public function setDomainRecordId($domainRecordId)
    {
        $this->domainRecordId = $domainRecordId;
    }

    /**
     * @return string
     */
    public function getIndexFilePath()
    {
        return $this->indexFilePath;
    }

    /**
     * @return int
     */
    public function getDomainRecordId()
    {
        return $this->domainRecordId;
    }

    /**
     * @return bool
     */
    public function execute()
    {
        $this->createSitemapFile();
        $this->createSitemapIndexFile();

        return true;
    }

    /**
     * @return string
     */
    public function getAdditionalInformation()
    {
        $format = $GLOBALS['LANG']->sL('LLL:EXT:dd_googlesitemap/locallang.xml:scheduler.extra_info');

        return sprintf($format, $this->indexFilePath);
    }

    protected function createSitemapIndexFile()
    {
        $tmpFileName = PATH_site . $this->indexFilePath . '.tmp';

        $indexFile = fopen($tmpFileName, 'wt');
        fwrite($indexFile, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL);
        fwrite($indexFile, '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL);
        fwrite($indexFile, '<sitemap><loc>' . htmlspecialchars($this->getSitemapFilePath()) . '</loc></sitemap>' . PHP_EOL);
        fwrite($indexFile, '</sitemapindex>' . PHP_EOL);
        fclose($indexFile);

        @unlink(PATH_site . $this->indexFilePath);
        rename($tmpFileName, PATH_site . $this->indexFilePath);
    }

    protected function createSitemapFile()
    {
        $sitemapFile = PATH_site . $this->getSitemapFilePath();
        GeneralUtility::mkdir_deep(dirname($sitemapFile));
        file_put_contents($sitemapFile, $this->getSitemapXml());
    }

    /**
     * @return string
     */
    protected function getSitemapFilePath()
    {
        $fileParts = pathinfo($this->indexFilePath);
        $filePath = $fileParts['dirname'] . '/' . $fileParts['filename'] . '_sitemap.xml';

        return $filePath;
    }

    /**
     * @return string
     */
    protected function getSitemapXml()
    {
        $content = '';
        $sysDomainRow = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('pid, domainName', 'sys_domain', 'uid ='. $this->domainRecordId);
        if (is_array($sysDomainRow)) {
            $_GET['id'] = $sysDomainRow['pid'];
            $GLOBALS['TT'] = new NullTimeTracker();

            ob_start();
            $entryPoint = new EntryPoint();
            ob_clean();
            $entryPoint->main();
            $content = ob_get_contents();
            ob_end_clean();

            // force HTTPS
            $baseUrl = 'http://' . $sysDomainRow['domainName'];
            $baseUrlHttps = 'https://' . $sysDomainRow['domainName'];
            $content = str_replace($baseUrl, $baseUrlHttps, $content);
        }

        header('Content-type: text/html');

        return $content;
    }
}
