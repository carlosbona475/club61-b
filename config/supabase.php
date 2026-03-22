<?php

$config = [
    'url'          => 'https://eezvwckzglsqlsomabvo.supabase.co',
    'anon_key'     => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImVlenZ3Y2t6Z2xzcWxzb21hYnZvIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzM2MTM3ODgsImV4cCI6MjA4OTE4OTc4OH0.qzXT4aVYFuLO5MW9Smdmgk4S-dLaYp0iNiyFSv9Q2x8',
    'service_key'  => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImVlenZ3Y2t6Z2xzcWxzb21hYnZvIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3MzYxMzc4OCwiZXhwIjoyMDg5MTg5Nzg4fQ.MHUrJwrY8w28iM1AgckIATJ3ubdUC9N6AzsgZEGAEzI',
];

if (!defined('SUPABASE_URL')) {
    define('SUPABASE_URL', rtrim($config['url'], '/'));
}

if (!defined('SUPABASE_ANON_KEY')) {
    define('SUPABASE_ANON_KEY', $config['anon_key']);
}

if (!defined('SUPABASE_SERVICE_KEY')) {
    define('SUPABASE_SERVICE_KEY', $config['service_key']);
}

return $config;
