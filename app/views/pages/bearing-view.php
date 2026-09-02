<?php
// Halaman lama "Trend" diarahkan ke Deteksi Anomali baru.
// Tujuannya: admin dan user melihat trend dari sumber yang sama, tanpa Python API lama.
require_login();
redirect('bearing-anomali');
