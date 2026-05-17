<?php

require __DIR__ . '/vendor/autoload.php';

use App\Analyzer\CakeV2PropertyExtractor;
use App\Analyzer\ProjectIndexer;
use App\Analyzer\CakeAnalyzer;
use App\Shared\JsonSchemaValidator;
use App\Generator\PromptGenerator;

echo "=== CakePrompt Engineering System v1.0 ===\n\n";

// Проверяем базовые аргументы CLI
if ($argc < 3) {
    echo "Использование:\n";
    echo "  docker-compose run --rm analyzer php app.php [путь_к_файлу] [тип_задачи]\n\n";
    echo "Пример:\n";
    echo "  docker-compose run --rm analyzer php app.php docs/UsersController.php REFACTOR\n";
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
echo "📋 Тип задачи: $taskType\n\n";

try {
    // 1. Индексация проекта для расчета Influence Score
    echo "⚙️  Инициализация глобального индексатора...\n";
    $extractor = new CakeV2PropertyExtractor();
    $indexer = new ProjectIndexer($extractor);
    $projectRoot = dirname($filePath, 2);
    
    echo "📂 Корень проекта определен как: $projectRoot\n";
    echo "⏳ Сканирование проекта и расчет Influence Score... ";
    $indexer->indexProject($projectRoot);
    echo "Готово!\n\n";

    // 2. Детальный статический анализ целевого файла
    echo "⏳ Запуск детального анализа файла...\n";
    $analyzer = new CakeAnalyzer($filePath, $indexer);
    $analysisResult = $analyzer->analyze();

    // 3. Жесткая валидация контракта данных по JSON-схеме
    echo "🛡️  Валидация структуры данных по JSON-схеме... ";
    $schemaPath = __DIR__ . '/src/Shared/analysis-schema.json';
    $validator = new JsonSchemaValidator($schemaPath);
    $validator->validate($analysisResult);
    echo "Успешно!\n\n";

    // 4. Генерация финального промпта для LLM (Модуль Generator)
    echo "🧠 Формирование оптимизированного промпта для LLM...\n";
    $generator = new PromptGenerator();
    $compiledPrompt = $generator->generate($analysisResult, $taskType);
    
    echo "==================== СКОМПИЛИРОВАННЫЙ ПРОМПТ ====================\n";
    echo $compiledPrompt . "\n";
    echo "=================================================================\n\n";

    // Сохраняем промпт в файл рядом с анализируемым файлом для удобства
    $promptFile = $filePath . '.' . strtolower($taskType) . '.prompt.txt';
    file_put_contents($promptFile, $compiledPrompt);
    echo "💾 Промпт также сохранен в файл: $promptFile\n\n";
    
    echo "✅ Работа системы успешно завершена!\n";

} catch (Exception $e) {
    echo "❌ Произошла ошибка во время работы системы:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}