<?php
// Copy this file to config.php (same folder as index.php) and fill it in.
// config.php is git-ignored, so your secrets never reach GitHub.
// Opening config.php in a browser shows nothing: PHP runs it and outputs no text.
return [
    'UPWORK_CLIENT_ID'      => '',  // "Key" on upwork.com/developer/keys
    'UPWORK_CLIENT_SECRET'  => '',  // "Secret" on upwork.com/developer/keys
    'UPWORK_REDIRECT_URI'   => 'https://www.outrightsystems.org/upwork/',  // exactly as registered on Upwork
    'ADMIN_PASSWORD'        => '',  // long password to open this page
    'JOB_FINDER_URL'        => '',  // e.g. https://upwork-job-finder.onrender.com
    'JOB_FINDER_IMPORT_KEY' => '',  // copy UPWORK_IMPORT_KEY from Render → upwork-job-finder → Environment
    'UPWORK_TENANT_ID'      => '',  // optional
];
