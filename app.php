<?php

require __DIR__ . '/vendor/autoload.php';

use App\Analyzer\CakeAnalyzer;
use App\Generator\PromptGenerator;

echo "=== CakePrompt Engineering System v1.0 ===\n\n";

// Проверяем базовые аргументы CLI
if ($argc < 3) {
    echo "Использование:\n";
    echo "  docker compose run --rm analyzer php app.php [путь_к_файлу] [тип_задачи]\n\n";
    echo "Пример:\n";
    echo "  docker compose run --rm analyzer php app.php /app/docs/examples/UsersController.php REFACTOR\n";
    exit(1);
}

$filePath = $argv[1];
$taskType = strtoupper($argv[2]);

// Валидация типа задачи
$allowedTypes = ['FEATURE', 'REFACTOR', 'DEBUG'];
if (!in_array($taskType, $allowedTypes)) {
    echo "Ошибка: Неверный тип задачи. Допустимы: " . implode(', ', $allowedTypes) . "\n";
    exit(1);
}

echo "🎯 Файл для анализа: $filePath\n";
echo "📋 Тип задачи: $taskType\n\n";

echo "⏳ Запуск Анализатора кода...\n";

try {
    $analyzer = new CakeAnalyzer($filePath);
    $analysisResult = $analyzer->analyze();

    // Превращаем результат в JSON с красивыми отступами
    $jsonOutput = json_encode($analysisResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
    // Проверяем валидность полученного JSON (базовая проверка)
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Ошибка генерации JSON: " . json_last_error_msg());
    }

    echo "✅ Анализ успешно завершен!\n\n";
    echo $jsonOutput . "\n";

} catch (Exception $e) {
    echo "❌ Произошла ошибка во время работы системы:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}