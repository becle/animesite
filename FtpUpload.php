<?php


use Doctrine\ORM\EntityManager;


class ProcessFTPUploadedMetadataMediado extends AbstractStoreCommonCommand
{
    /**
     * リリースしたeISBN保存用配列
     */
    private $row_errors = array();

    const RESULT_FILE_DIR = '/data/alsys/manga/mediado/';

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription(<<<DESC
explainexplainexplainexplainexplainexplainexplainexplainexplainexplainexplain
explainexplainexplainexplainexplainexplainexplainexplainexplainexplain
DESC
            );
    }

    protected function doExecute()
    {
        if ($this->isDryRun()) {
            $this->writeln(sprintf("DryRun Mode: only showing information, not inserting data into DB"));
        }
        $parsed_rows = array();
        $parsed_rows_array = array();
        $this->row_errors = array();
        $this->result_struct = new ResultStruct();

        $hoge_publisher_id = PublisherConstant::HOGEDO_ID;
        $publisher = Context::getEntityManagerWrite()->find('Hoge:StorePublisher', $hoge_publisher_id);

        $metadata_processors = MetadataProcessorFactory::createProcessors(PublisherConstant::MEDIA_DO_ID);

        if(empty($metadata_processors)){
            $this->writeln("Data not found to upload.");
        }

        foreach ($metadata_processors as $processor) {
            $this->writeln(sprintf("Start processing %s.", $processor->getFullPath()));

            $sha1 = sha1_file($processor->getFullPath());

            $uploaded_file = Context::getEntityManagerWrite()
                ->getRepository('Mal:StoreFTPUploadedMetadataFile')
                ->findOneBy(['file_sha1' => $sha1]);

            try {
                if (!$uploaded_file) {
                    $this->writeln("This metadata is new! (sha1: $sha1)");

                    if (!$this->isDryRun()) {
                        $uploaded_file = $this->backupMetadata($publisher, $processor->getFullPath(), $sha1);
                    }
                } elseif ($uploaded_file->getStatus() === FTPUploadedMetadataFileConstant::STATUS_COMPLETED) {
                    $this->writeln("This metadata has already been processed successfully. (sha1: $sha1)");
                    $this->status_array[] = array(
                        $processor->getFullPath(), 
                        OpeConstant::FTP_UPLOAD_ERROR_CODE_103, 
                        OpeConstant::ERROR_REASON_FTP_UPLOAD[OpeConstant::FTP_UPLOAD_ERROR_CODE_103],
                        OpeConstant::ERROR_RESULT_FTP_UPLOAD[OpeConstant::FTP_UPLOAD_ERROR_CODE_103]);
                    continue;
                }

                $this->setResultStruct($parsed_rows, $processor);

                $parsed_rows_success = $this->checkIfRelatedFilesExist($parsed_rows_success);

                if (!$this->isDryRun()) {

                    FTPService::moveFileToDeletionDirectory($processor->getFullPath());
                    $this->writeln('Moved the file to the deletion directory.');

                    $this->writeln('Successfully processed the metadata!');
                }
            } catch (\Exception $e) {
                $this->status_array[] = array(
                    $processor->getFullPath(), 
                    OpeConstant::FTP_UPLOAD_ERROR_CODE_199, 
                    $e->getMessage(),
                    $e->getCode());
                
                $this->result_struct->file_path = $processor->getFullPath();
                $this->result_struct->error_code = $e->getCode();
                $this->result_struct->error_msg = $e->getMessage();
                $this->result_struct->add();
                continue;
            }
            //success
            if(empty($this->result_struct)){
                $this->status_array[] = array(
                    $processor->getFullPath(), 
                    OpeConstant::FTP_UPLOAD_SUCCESS_CODE, 
                    OpeConstant::ERROR_REASON_FTP_UPLOAD[OpeConstant::FTP_UPLOAD_SUCCESS_CODE],
                    OpeConstant::ERROR_RESULT_FTP_UPLOAD[OpeConstant::FTP_UPLOAD_SUCCESS_CODE]);
            }

        }
        if (!$this->isDryRun()) {
        
            $this->outputResult($this->status_array);

        }//dryrun
    }

}
