import os
import re

php_files = []
for root, dirs, files in os.walk('.'):
    if any(p in root for p in ['tests', '.git', 'scratch']):
        continue
    for f in files:
        if f.endswith('.php'):
            php_files.append(os.path.join(root, f))

print(f"Scanning {len(php_files)} PHP files for PHP 8-only features...")

patterns = [
    (r'\?->', 'Nullsafe operator (?->)'),
    (r'\bmatch\s*\(', 'Match expression (match ())'),
    (r'#\[[A-Za-z0-9_\\\s,\(\)]+\]', 'PHP 8 Attribute (#[...])'),
    (r'function\s+[a-zA-Z0-9_]+\s*\([^)]*?(?:public|protected|private)\s+', 'Constructor property promotion'),
    (r'\bstr_contains\s*\(', 'str_contains() built-in (PHP 8.0+)'),
    (r'\bstr_starts_with\s*\(', 'str_starts_with() built-in (PHP 8.0+)'),
    (r'\bstr_ends_with\s*\(', 'str_ends_with() built-in (PHP 8.0+)'),
    (r'\bfdiv\s*\(', 'fdiv() built-in (PHP 8.0+)'),
    (r'\bget_debug_type\s*\(', 'get_debug_type() built-in (PHP 8.0+)'),
    (r':\s*[a-zA-Z0-9_\\]+\|[a-zA-Z0-9_\\]+', 'Union return type'),
    (r'\b[a-zA-Z0-9_\\]+\|[a-zA-Z0-9_\\]+\s+\$[a-zA-Z0-9_]+', 'Union parameter type'),
]

issues = 0
for path in php_files:
    with open(path, 'r', encoding='utf-8', errors='ignore') as f:
        lines = f.readlines()
    for line_idx, line in enumerate(lines, 1):
        # strip comments
        clean_line = line.split('//')[0].strip()
        if clean_line.startswith('*') or clean_line.startswith('/*'):
            continue
        for pat, desc in patterns:
            if re.search(pat, clean_line):
                print(f"[FAIL] {path}:{line_idx} - {desc}: {line.strip()}")
                issues += 1

if issues == 0:
    print("[PASS] 0 PHP 8-only features found. All files compatible with PHP 7.4+.")
else:
    print(f"[FAIL] Found {issues} potential PHP 8-only features.")
