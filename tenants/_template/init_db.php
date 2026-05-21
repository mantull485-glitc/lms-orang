<?php
// ============================================================
// DEPRECATED — Legacy MySQL Initialization Script
// Tidak digunakan lagi sejak migrasi ke Supabase (PostgreSQL).
// Schema dikelola via: config/supabase_schema.sql
// Jalankan script tersebut di Supabase SQL Editor.
// ============================================================
http_response_code(410);
echo "<h2>File ini sudah tidak aktif.</h2>";
echo "<p>Schema database dikelola via <code>config/supabase_schema.sql</code>.</p>";
exit;
