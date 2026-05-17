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
    echo "❌ Ошибка: Неверный тип задачи. Допустимы: " . implode(', ', $allowedTypes) . "\n";
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

    // 4. Генерация финального промпта для LLM
    echo "🧠 Формирование оптимизированного промпта для LLM... ";
    $generator = new PromptGenerator();
    $compiledPrompt = $generator->generate($analysisResult, $taskType);
    echo "Готово!\n\n";
    
    // =========================================================================
    // НАСТРОЙКА ВЫДЕЛЕННОГО ХРАНИЛИЩА ПРОМПТОВ
    // =========================================================================
    $baseOutputDir = __DIR__ . '/.prompt_output';
    
    // Очищаем путь к анализируемому файлу от лишних слэшей в начале для склейки
    $relativeLogPath = ltrim($filePath, '/'); 
    
    // Формируем целевую папку: .prompt_output/sic/app/Controller/SicsController.php/
    $targetFolder = $baseOutputDir . '/' . $relativeLogPath;
    
    // Автоматически создаем рекурсивную структуру папок, если её еще нет
    if (!is_dir($targetFolder)) {
        mkdir($targetFolder, 0775, true);
    }
    
    // Имя файла: refactor.prompt.txt
    $promptFileName = strtolower($taskType) . '.prompt.txt';
    $finalPromptPath = $targetFolder . '/' . $promptFileName;
    
    // Запись промпта на диск
    if (file_put_contents($finalPromptPath, $compiledPrompt) !== false) {
        echo "💾 [УСПЕХ] Промпт успешно сгенерирован и изолирован!\n";
        echo "👉 Абсолютный путь: {$finalPromptPath}\n";
        echo "📂 Папка в контейнере: /.prompt_output/{$relativeLogPath}/\n\n";
    } else {
        echo "❌ [ОШИБКА] Не удалось записать скомпилированный промпт на диск.\n\n";
    }
    
    echo "✅ Работа системы успешно завершена!\n";
    exit(0);

} catch (Exception $e) {
    echo "❌ Произошла ошибка во время работы системы:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}