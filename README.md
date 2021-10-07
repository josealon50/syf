SynchronyReconJob
    High Level Logic:
        - Process will read all files from RECONCILIATION, it will process each file at a time. While reading the reconciliation file it will do a query using the AMT and APPROVAL CODE to query SO. After finding
          a record on SO. It will also check if the transaction has already been process or staged into ASP_RECON. After the check of ASP_RECON it will load all valid record into ASP_RECON. Process will then query ASP_RECON for 
          records with statuses of 'H' and finance company of 'SYF' it will load all records into AR_TRN. It will then email a summary of transactions posted and a file error.

HOW TO USE MODES
    - php SynchronyReconJob.php 0 RUN NORMAL
    - php SynchronyReconJob.php 1 Audit inserts into AR_TRN. Mode will insert into ASP_RECON.
    - php SynchronyReconJob.php 2 Process all staged records on ASP_RECON with status of H

