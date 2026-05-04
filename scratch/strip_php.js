const fs = require('fs');

let code = fs.readFileSync('scratch/test.js', 'utf8');

// Replace PHP echoes within assignments: `var x = <?php ... ?>;`
code = code.replace(/<\?php[\s\S]*?\?>/g, (match) => {
    // If it looks like an echo or expression, replace with null
    if (match.includes('echo') || match.includes('=')) {
        return 'null';
    }
    // Otherwise replace with a block comment to maintain newlines loosely
    return '/*PHP*/';
});

fs.writeFileSync('scratch/test_clean.js', code);
