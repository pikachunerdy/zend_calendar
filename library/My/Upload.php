<?php

/**
 * Created by Simon Speich, www.speich.net
 * Date: 07.02.11
 */

class My_Upload
{
    var $numWrittenBytes = 0;
    var $fileName = null;
    
    
    public function setLogger($log)
    {
        $this->log = $log;
    }
    
    public function setMasters($master)
    {
        $this->masters = $master;
    }

    /**
     * Delete uploaded file.
     * @param string $file file name
     * @param string $dir upload directory
     * @return void
     */
    public function delete($file, $dir)
    {
        $protocol = $_SERVER["SERVER_PROTOCOL"];
        // Important: You should also add a session variable to make sure that a the user can only delete his own files!
        if (is_file($dir . $file)) {
            $succ = unlink($dir . $file);
            if ($succ) {
                header($protocol . ' 200 OK');
                exit('File deleted.');
            } else {
                header($protocol . '500 Internal Server Error');
                exit('Deleting failed');
            }
        } else {
            header($protocol . ' 404 Not Found');
            exit('File does not exist.');
        }
    }

    /**
     * Save uploaded file to disk.
     * Note: In this demo files are not written to disk. You have to uncomment the corresponding lines.
     * @param string dir upload directory
     * @param bool $append append to file (resume)
     * @return void
     */
    public function save($dir, $append = false)
    {
        $headers = getallheaders();
        $protocol = $_SERVER["SERVER_PROTOCOL"];
        
        if (!isset($headers['Content-Length'])) {
            header($protocol . ' 411 Length Required');
            exit('Header \'Content-Length\' not set.');
        }
        /*if (isset($headers['Content-Type'], $headers['X-File-Size'], $headers['X-File-Name']) &&
         ($headers['Content-Type'] === 'multipart/form-data' || $headers['Content-Type'] === 'application/octet-stream; charset=UTF-8')) {*/
        if (isset($headers['X-File-Size'], $headers['X-File-Name'])) {
            // Sanitize all uploaded headers before saving to disk
            // Enable writing to disk at your own risk! Special care needs to be taken, that only the right person can
            // save/append a file. Also the type is not checked, a user can upload anything!
            $file = new stdClass();
            $file->name = preg_replace('/[^ \.\w_\-]*/', '', basename($headers['X-File-Name']));
            $file->size = preg_replace('/\D*/', '', $headers['X-File-Size']);
            // php://input bypasses the ini settings, we have to limit the file size ourselves:
            // Find smallest init setting and set upload limit accordingly.
            $maxUpload = $this->getBytes(ini_get('upload_max_filesize')); // can only be set in php.ini and not by ini_set()
            $maxPost = $this->getBytes(ini_get('post_max_size')); // can only be set in php.ini and not by ini_set()
            $memoryLimit = $this->getBytes(ini_get('memory_limit'));
            $limit = min($maxUpload, $maxPost, $memoryLimit);
            if ($headers['Content-Length'] > $limit) {
                header($protocol . ' 403 Forbidden');
                exit('File size to big. Limit is ' . $limit . ' bytes.');
            }
            
            $this->fileName = $file->name;
            $file->content = file_get_contents("php://input");
            // Since I don't know if the header content-length can be spoofed/is reliable, I check the file size again after it is uploaded
            if (mb_strlen($file->content) > $limit) {
                header($protocol . ' 403 Forbidden');
                exit('Nice try.');
            }
            // Uncomment if you want to write to server. Make sure you have the right permissions.
            $flags = $append ? FILE_APPEND : 0;
            $this->numWrittenBytes = file_put_contents($dir . $file->name, $file->content, $flags);
            
            if ($this->numWrittenBytes !== false) {
                $this->fileProcess($dir . $file->name);
                header($protocol . ' 201 Created');
                exit('File written.');
            } else {
                header($protocol . ' 505 Internal Server Error');
                exit('Error writing file');
            }
        } else {
            header($protocol . ' 500 Internal Server Error');
            exit('Correct headers are not set.');
        }
    }

    /**
     * @see http://ch2.php.net/manual/en/function.ini-get.php
     * @param  $val
     * @return int|string
     */
    public function getBytes($val)
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        switch ($last) {
            // The 'G' modifier is available since PHP 5.1.0
            
        case 'g':
            $val*= 1024;
        case 'm':
            $val*= 1024;
        case 'k':
            $val*= 1024;
        }
        return $val;
    }
    
    public function getNumWrittenBytes()
    {
        return $this->numWrittenBytes;
    }
    
    private function fileProcess($location)
    {
        $this->log->info("File location: " . $location);
        $this->log->info("File name: " . basename($location));
        list($subject, $grade, $extension) = explode('.', basename($location));
        $subject = preg_replace('/^tmp/', '', $subject);
        $this->log->info('Subject: ' . $subject . ' Grade: ' . $grade . ' Extension: ' . $extension);
        $csv = new My_CSV_Reader();
        $csv->setLogger($this->log);
        $processed = $csv->parseFile($location);
        $this->log->info('Info converted to array: ' . (!empty($processed)));
        $this->masters->delete(array('grade = ?' => $grade, 'subject = ?' => $subject));
        
        foreach ($processed as $row) {
            if (is_array($row)) {
                $data['Standard-Code'] = $row['Standard-Code'];
                $data['Offset'] = $row['Offset'];
                $data['Duration'] = $row['Duration'];
                $data['PO Title'] = $row['PO Title'];
                $data['URL'] = $row['URL'];
                $data['Note'] = $row['Note'];
                if (!$this->array_empty($data)) {
                    $data['grade'] = $grade;
                    $data['subject'] = $subject;
                    $this->masters->insert($data);
                }
                unset($data);
            }
        }
        unlink($location);
    }
    
    private function array_empty($mixed)
    {
        if (is_array($mixed)) {
            foreach ($mixed as $value) {
                if (!$this->array_empty($value)) {
                    return false;
                }
            }
        } elseif (!empty($mixed)) {
            return false;
        }
        return true;
    }
}
