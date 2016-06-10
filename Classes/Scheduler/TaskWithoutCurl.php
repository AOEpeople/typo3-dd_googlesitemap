<?php
namespace DmitryDulepov\DdGooglesitemap\Scheduler;

use DmitryDulepov\DdGooglesitemap\Generator\EntryPoint;
use TYPO3\CMS\Core\TimeTracker\NullTimeTracker;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

class TaskWithoutCurl extends AbstractTask
{
    /** @var string */
    protected $indexFilePath;

    /** @var integer */
    protected $domainRecordId;

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
        file_put_contents(PATH_site . $this->getSitemapFilePath(), $this->getSitemapXml());
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

            $entryPoint = new EntryPoint();
            ob_start();
            $entryPoint->main();
            $content = ob_get_contents();
            ob_end_clean();

            // force HTTPS
            $baseUrl = 'http://'.$sysDomainRow['domainName'];
            $baseUrlHttps = 'https://'.$sysDomainRow['domainName'];
            $content = str_replace($baseUrl, $baseUrlHttps, $content);
        }

        return $content;
    }
}
