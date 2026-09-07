import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const apiPhpPath = path.join(__dirname, '../routes/api.php');
const controllersPath = path.join(__dirname, '../app/Http/Controllers');

let apiContent = fs.readFileSync(apiPhpPath, 'utf8');

// Find all usages like ControllerName::class
const usages = [...apiContent.matchAll(/([A-Za-z0-9_]+Controller)::class/g)].map(m => m[1]);
const uniqueUsages = [...new Set(usages)];

// Find all imports
const imports = [...apiContent.matchAll(/use\s+(.*?\\([A-Za-z0-9_]+Controller));/g)];
const importedClasses = imports.map(m => m[2]);

const missing = uniqueUsages.filter(u => !importedClasses.includes(u));

if (missing.length === 0) {
    console.log("No missing imports found!");
    process.exit(0);
}

console.log("Missing controllers:", missing);

function findControllerPath(dir, controllerName) {
    const files = fs.readdirSync(dir, { withFileTypes: true });
    for (const file of files) {
        const fullPath = path.join(dir, file.name);
        if (file.isDirectory()) {
            const found = findControllerPath(fullPath, controllerName);
            if (found) return found;
        } else if (file.name === controllerName + '.php') {
            return fullPath;
        }
    }
    return null;
}

const newImports = [];
for (const controller of missing) {
    const file = findControllerPath(controllersPath, controller);
    if (file) {
        // Convert to namespace: App\Http\Controllers\...
        const relative = path.relative(controllersPath, file);
        const namespacePath = relative.replace(/\\/g, '/').replace('.php', '').split('/').join('\\');
        newImports.push(`use App\\Http\\Controllers\\${namespacePath};`);
    } else {
        console.log(`Could not find file for ${controller}`);
    }
}

if (newImports.length > 0) {
    // Add to top of file
    const newContent = apiContent.replace('use Illuminate\\Http\\Request;', 'use Illuminate\\Http\\Request;\n' + newImports.join('\n'));
    fs.writeFileSync(apiPhpPath, newContent, 'utf8');
    console.log("Added imports:\n" + newImports.join('\n'));
}
