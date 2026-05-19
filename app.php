<?php

require __DIR__ . '/vendor/autoload.php';

use App\Analyzer\CakeV2PropertyExtractor;
use App\Analyzer\ProjectIndexer;
use App\Analyzer\CakeAnalyzer;
use App\Shared\JsonSchemaValidator;
use App\Generator\PromptGenerator;

echo "=== CakePrompt Engineering System v1.0 ===\n\n";

// Variables for storing flags and positional arguments
$positionalArgs = [];
$customInstruction = '';

// Parse command line arguments (skip $argv[0] — script name)
for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    
    if (strpos($arg, '--instruction=') === 0) {
        // Extract value from --instruction="text" flag
        $customInstruction = substr($arg, 14);
    } else {
        // Collect standard positional arguments
        $positionalArgs[] = $arg;
    }
}

// Check for required positional parameters (path and task type)
if (count($positionalArgs) < 2) {
    echo "Usage:\n";
    echo "  docker-compose run --rm analyzer php app.php [file_path] [task_type] [--instruction=\"Your instruction\"]\n\n";
    echo "Examples:\n";
    echo "  docker-compose run --rm analyzer php app.php docs/UsersController.php REFACTOR\n";
    echo "  docker-compose run --rm analyzer php app.php docs/UsersController.php REFACTOR --instruction=\"Move ID to config\"\n";
    exit(1);
}

$filePath = $positionalArgs[0];
$taskType = strtoupper($positionalArgs[1]);

// Task type validation
$allowedTypes = ['FEATURE', 'REFACTOR', 'DEBUG'];
if (!in_array($taskType, $allowedTypes)) {
    echo "❌ Error: Invalid task type. Allowed: " . implode(', ', $allowedTypes) . "\n";
    exit(1);
}

// Check physical file existence before running heavy processes
if (!file_exists($filePath)) {
    echo "❌ Error: File not found at path: $filePath\n";
    exit(1);
}

echo "🎯 File for analysis: $filePath\n";
echo "📋 Task type: $taskType\n";
if (!empty($customInstruction)) {
    echo "📝 Custom instruction: \"$customInstruction\"\n";
}
echo "\n";

try {
    // 1. Project indexing for Influence Score calculation
    echo "⚙️  Initializing global indexer...\n";
    $extractor = new CakeV2PropertyExtractor();
    $indexer = new ProjectIndexer($extractor);
    $projectRoot = dirname($filePath, 2);
    
    echo "📂 Project root determined as: $projectRoot\n";
    echo "⏳ Scanning project and calculating Influence Score... ";
    $indexer->indexProject($projectRoot);
    echo "Done!\n\n";

    // 2. Detailed static analysis of target file
    echo "⏳ Running detailed file analysis...\n";
    $analyzer = new CakeAnalyzer($filePath, $indexer);
    $analysisResult = $analyzer->analyze();

    // 3. Strict data contract validation against JSON schema
    echo "🛡️  Validating data structure against JSON schema... ";
    $schemaPath = __DIR__ . '/src/Shared/analysis-schema.json';
    $validator = new JsonSchemaValidator($schemaPath);
    $validator->validate($analysisResult);
    echo "Success!\n\n";

    // 4. Final prompt generation for LLM (pass custom instruction)
    echo "🧠 Formulating optimized prompt for LLM... ";
    $generator = new PromptGenerator();
    $compiledPrompt = $generator->generate($analysisResult, $taskType, $customInstruction);
    echo "Done!\n\n";
    
    // =========================================================================
    // DEDICATED PROMPT STORAGE CONFIGURATION
    // =========================================================================
    $baseOutputDir = __DIR__ . '/.prompt_output';
    
    // Remove leading slashes from analyzed file path for concatenation
    $relativeLogPath = ltrim($filePath, '/'); 
    
    // Form target folder: .prompt_output/sic/app/Controller/SicsController.php/
    $targetFolder = $baseOutputDir . '/' . $relativeLogPath;
    
    // Automatically create recursive folder structure if it doesn't exist
    if (!is_dir($targetFolder)) {
        mkdir($targetFolder, 0775, true);
    }
    
    // File name: refactor.prompt.txt
    $promptFileName = strtolower($taskType) . '.prompt.txt';
    $finalPromptPath = $targetFolder . '/' . $promptFileName;
    
    // Write prompt to disk
    if (file_put_contents($finalPromptPath, $compiledPrompt) !== false) {
        echo "💾 [SUCCESS] Prompt successfully generated and isolated!\n";
        echo "👉 Absolute path: {$finalPromptPath}\n";
        echo "📂 Folder in container: /.prompt_output/{$relativeLogPath}/\n\n";
    } else {
        echo "❌ [ERROR] Failed to write compiled prompt to disk.\n\n";
    }
    
    echo "✅ System work successfully completed!\n";
    exit(0);

} catch (Exception $e) {
    echo "❌ An error occurred during system operation:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}