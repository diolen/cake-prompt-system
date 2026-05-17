<?php

require __DIR__ . '/vendor/autoload.php';

use App\Analyzer\CakeV2PropertyExtractor;
use App\Analyzer\ProjectIndexer;
use App\Analyzer\CakeAnalyzer;

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

// Проверяем физическое существование файла перед запуском тяжелых процессов
if (!file_exists($filePath)) {
    echo "❌ Ошибка: Файл не найден по пути: $filePath\n";
    exit(1);
}

echo "🎯 Файл для анализа: $filePath\n";
echo "📋 Тип задачи: $taskType\n";

// ... код инициализации индексатора и анализатора ...

try {
    // 1. Индексация (как в Задаче А)
    echo "⚙️  Инициализация глобального индексатора...\n";
    $extractor = new CakeV2PropertyExtractor();
    $indexer = new ProjectIndexer($extractor);
    $projectRoot = dirname($filePath, 2);
    
    $indexer->indexProject($projectRoot);
    echo "Готово!\n\n";

    // 2. Детальный анализ
    echo "⏳ Запуск детального анализа файла...\n";
    $analyzer = new CakeAnalyzer($filePath, $indexer);
    $analysisResult = $analyzer->analyze();

    // 3. Жесткая валидация контракта данных (НОВОЕ В ЗАДАЧЕ Б)
    echo "🛡️  Валидация структуры данных по JSON-схеме... ";
    $schemaPath = __DIR__ . '/src/Shared/analysis-schema.json';
    $validator = new App\Shared\JsonSchemaValidator($schemaPath);
    $validator->validate($analysisResult);
    echo "Успешно!\n\n";

    // 4. Превращаем результат в JSON
    $jsonOutput = json_encode($analysisResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
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