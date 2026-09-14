import os
import zipfile
import subprocess
import sys

base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
zip_path = os.path.join(base_dir, "ewa-ai-string-assistant-for-loco-translate.zip")
extract_dir = os.path.join(base_dir, "extracted_package_test")

if os.path.exists(extract_dir):
    import shutil
    shutil.rmtree(extract_dir)
os.makedirs(extract_dir, exist_ok=True)

print("=======================================================")
print("   EWA AI STRING ASSISTANT - FINAL PACKAGE VERIFICATION")
print("=======================================================\n")

# 1. Inspect ZIP structure
print("--- 1. Inspecting ZIP Structure ---")
with zipfile.ZipFile(zip_path, 'r') as zf:
    namelist = zf.namelist()
    print(f"Total entries in ZIP: {len(namelist)}")
    bad_prefixes = [name for name in namelist if not name.startswith("ewa-ai-string-assistant-for-loco-translate/")]
    if bad_prefixes:
        print(f"[FAIL] Entries without root prefix: {bad_prefixes}")
        sys.exit(1)
    else:
        print("[PASS] All files rooted under 'ewa-ai-string-assistant-for-loco-translate/'.")

    # Check for forbidden files
    forbidden = [name for name in namelist if any(x in name.lower() for x in ['.git', '.env', 'node_modules', 'tests', 'scratch', '.vscode', '.idea', 'build-zip'])]
    if forbidden:
        print(f"[FAIL] Forbidden files in ZIP: {forbidden}")
        sys.exit(1)
    else:
        print("[PASS] 0 forbidden files (.git, .env, tests, scratch, IDE files).")

    zf.extractall(extract_dir)

plugin_root = os.path.join(extract_dir, "ewa-ai-string-assistant-for-loco-translate")

# 2. php -l on all extracted PHP files
print("\n--- 2. php -l Syntax Check on Extracted Files ---")
php_files = []
for root, dirs, files in os.walk(plugin_root):
    for f in files:
        if f.endswith('.php'):
            php_files.append(os.path.join(root, f))

php_errors = 0
for pf in php_files:
    rel = os.path.relpath(pf, plugin_root)
    res = subprocess.run(["php", "-l", pf], capture_output=True, text=True)
    if res.returncode != 0:
        print(f"[FAIL] Syntax error in {rel}: {res.stderr or res.stdout}")
        php_errors += 1
    else:
        print(f"[PASS] php -l: {rel}")

if php_errors > 0:
    print(f"[FAIL] {php_errors} PHP syntax errors detected.")
    sys.exit(1)

# 3. Static search in extracted files
print("\n--- 3. Static Search in Extracted Package ---")
prohibited = [
    (r'<<<', 'HEREDOC/NOWDOC'),
    (r'wp_ajax_ewa(?!as)', 'Legacy AJAX action prefix'),
    (r'\bewaAdmin\b', 'Legacy ewaAdmin JS global'),
    (r'\bewaLoco\b', 'Legacy ewaLoco JS global'),
    (r'\bEWA_[A-Z0-9_]+', 'Legacy EWA_ constant'),
]

static_fails = 0
import re
for root, dirs, files in os.walk(plugin_root):
    for f in files:
        if f.endswith(('.php', '.js', '.css', '.txt')):
            path = os.path.join(root, f)
            rel = os.path.relpath(path, plugin_root)
            with open(path, 'r', encoding='utf-8', errors='ignore') as fh:
                for line_num, line in enumerate(fh, 1):
                    for pat, label in prohibited:
                        if re.search(pat, line):
                            print(f"[FAIL] {label} in {rel}:{line_num}: {line.strip()}")
                            static_fails += 1

if static_fails == 0:
    print("[PASS] 0 prohibited patterns found in extracted package.")
else:
    print(f"[FAIL] {static_fails} prohibited occurrences found.")
    sys.exit(1)

# 4. Readme metadata check
print("\n--- 4. Readme Metadata Check ---" )
readme_path = os.path.join(plugin_root, "readme.txt")
with open(readme_path, 'r', encoding='utf-8') as f:
    readme_text = f.read()

assert "Contributors: errorwebagency" in readme_text, "Missing Contributors in readme"
assert "Requires at least: 6.0" in readme_text, "Missing Requires at least in readme"
assert "Tested up to: 7.1" in readme_text, "Missing Tested up to 7.1 in readme"
assert "Requires PHP: 7.4" in readme_text, "Missing Requires PHP 7.4 in readme"
assert "Stable tag: 1.8.0" in readme_text, "Missing Stable tag in readme"
assert "== External Services ==" in readme_text, "Missing External Services header"
assert "https://openai.com/policies/services-agreement/" in readme_text, "Missing OpenAI Services Agreement"
assert "https://openai.com/policies/service-terms/" in readme_text, "Missing OpenAI Service Terms"
assert "https://openai.com/policies/privacy-policy/" in readme_text, "Missing OpenAI Privacy Policy"
print("[PASS] Readme metadata and External Services links verified.")

# Clean up extracted dir after tests
import shutil
shutil.rmtree(extract_dir)
print("\n[PASS] Extracted package test directory cleaned up.")
print("\n=======================================================")
print("   ALL FINAL PACKAGE INTEGRITY TESTS PASSED!")
print("=======================================================")
