import os
import re

prod_files = []
for root, dirs, files in os.walk('.'):
    # Exclude non-production directories
    if any(p in root for p in ['tests', '.git', 'scratch', 'node_modules']):
        continue
    for f in files:
        if f.endswith(('.php', '.js', '.css', '.txt')):
            prod_files.append(os.path.join(root, f))

print(f"Auditing {len(prod_files)} production files...")

searches = [
    ("wp_ajax_ewa[a-z_]*", "AJAX action prefix check"),
    ("ewaAdmin", "Legacy JS admin global"),
    ("ewaLoco", "Legacy JS loco global"),
    ("EWA_[A-Z0-9_]+", "Legacy constant prefix"),
    ("<<<", "HEREDOC / NOWDOC check"),
    ("api\\.openai\\.com", "OpenAI endpoint reference"),
    ("openrouter", "OpenRouter references"),
    ("ollama", "Ollama references"),
    ("wp_remote_", "WordPress HTTP API calls"),
    ("file_put_contents", "Direct file writes"),
    ("file_get_contents", "Direct file reads"),
    ("rename\\(", "File rename calls"),
    ("chmod\\(", "File permissions calls"),
    ("unlink\\(", "File deletion calls"),
    ("load_plugin_textdomain", "Textdomain loading"),
    ("admin_notices", "Admin notices hook"),
    ("register_setting", "Settings registration"),
    ("update_option", "Option updates"),
    ("set_transient", "Transient sets"),
    ("Authorization", "Auth headers"),
    ("api_key", "API key references"),
]

for pat, desc in searches:
    matches = []
    for path in prod_files:
        with open(path, 'r', encoding='utf-8', errors='ignore') as f:
            for idx, line in enumerate(f, 1):
                if re.search(pat, line):
                    matches.append((path, idx, line.strip()))
    print(f"\n=== {desc} (pattern: {pat}) -> {len(matches)} occurrences ===")
    for p, idx, l in matches[:10]:
        print(f"  {p}:{idx}: {l[:100]}")
    if len(matches) > 10:
        print(f"  ... and {len(matches) - 10} more.")

# Specific check for ewa_
ewa_matches = []
for path in prod_files:
    if path.endswith('.txt') or path.endswith('.pot'):
        continue
    with open(path, 'r', encoding='utf-8', errors='ignore') as f:
        for idx, line in enumerate(f, 1):
            # check for ewa_ but NOT ewaas_ and NOT ewa- (css class)
            for m in re.finditer(r'\bewa_([a-zA-Z0-9_]+)', line):
                matched = m.group(0)
                ewa_matches.append((path, idx, matched, line.strip()))

print(f"\n=== Legacy ewa_* check (excluding ewaas_ and ewa- CSS/HTML) -> {len(ewa_matches)} occurrences ===")
for p, idx, m, l in ewa_matches:
    print(f"  {p}:{idx}: [{m}] -> {l[:100]}")
