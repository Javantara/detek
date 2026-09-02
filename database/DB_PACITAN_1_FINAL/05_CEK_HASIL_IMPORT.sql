USE db_pacitan_1;

SELECT 'sensor' AS tabel, COUNT(*) AS total FROM sensor
UNION ALL SELECT 'aktual', COUNT(*) FROM aktual
UNION ALL SELECT 'XGBoost__model_ai', COUNT(*) FROM `XGBoost__model_ai`
UNION ALL SELECT 'XGBoost__prediksi', COUNT(*) FROM `XGBoost__prediksi`
UNION ALL SELECT 'XGBoost__evaluasi_model_ai', COUNT(*) FROM `XGBoost__evaluasi_model_ai`
UNION ALL SELECT 'Deep_Learning__prediksi_autoencoder', COUNT(*) FROM `Deep_Learning__prediksi_autoencoder`
UNION ALL SELECT 'Deep_Learning__evaluasi_model_ai', COUNT(*) FROM `Deep_Learning__evaluasi_model_ai`;

SHOW TABLES;
