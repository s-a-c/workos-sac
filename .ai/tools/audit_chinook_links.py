#!/usr/bin/env python3
"""
Chinook Documentation Link Audit Script
Systematically checks all markdown files for broken internal and external links
"""

import os
import re
from pathlib import Path
from urllib.parse import urlparse
import urllib.request
import urllib.error
import time

def find_markdown_files(directory):
    """Find all markdown files in the directory"""
    md_files = []
    for root, dirs, files in os.walk(directory):
        for file in files:
            if file.endswith('.md'):
                md_files.append(os.path.join(root, file))
    return sorted(md_files)

def extract_links(content):
    """Extract all markdown links from content"""
    # Pattern for markdown links: [text](url)
    link_pattern = r'\[([^\]]*)\]\(([^)]+)\)'
    links = re.findall(link_pattern, content)
    return links

def check_internal_link(link_url, base_file_path, base_directory):
    """Check if internal link exists"""
    if link_url.startswith('#'):
        # Anchor link - would need content parsing to verify
        return True, "Anchor link (not verified)"

    if link_url.startswith('http'):
        return True, "External link"

    # Resolve relative path
    base_dir = os.path.dirname(base_file_path)
    full_path = os.path.normpath(os.path.join(base_dir, link_url))

    if os.path.exists(full_path):
        return True, "File exists"
    else:
        return False, f"File not found: {full_path}"

def check_external_link(url, timeout=10):
    """Check if external link is accessible"""
    try:
        req = urllib.request.Request(url, method='HEAD')
        with urllib.request.urlopen(req, timeout=timeout) as response:
            return True, f"HTTP {response.getcode()}"
    except urllib.error.HTTPError as e:
        return False, f"HTTP {e.code}"
    except urllib.error.URLError as e:
        return False, f"URL Error: {str(e)}"
    except Exception as e:
        return False, f"Request failed: {str(e)}"

def audit_file(file_path, base_directory):
    """Audit a single markdown file for broken links"""
    results = {
        'file': file_path,
        'total_links': 0,
        'broken_links': [],
        'working_links': [],
        'errors': []
    }

    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()

        links = extract_links(content)
        results['total_links'] = len(links)

        for link_text, link_url in links:
            if link_url.startswith('http'):
                # External link
                is_working, status = check_external_link(link_url)
                time.sleep(0.5)  # Rate limiting
            else:
                # Internal link
                is_working, status = check_internal_link(link_url, file_path, base_directory)

            link_info = {
                'text': link_text,
                'url': link_url,
                'status': status
            }

            if is_working:
                results['working_links'].append(link_info)
            else:
                results['broken_links'].append(link_info)

    except Exception as e:
        results['errors'].append(f"Error processing file: {str(e)}")

    return results

def main():
    base_directory = "/Users/s-a-c/Herd/workos-sac/.ai/guides/chinook/"

    print("🔍 Starting Chinook Documentation Link Audit")
    print(f"📁 Base directory: {base_directory}")
    print("=" * 60)

    # Find all markdown files
    md_files = find_markdown_files(base_directory)
    print(f"📄 Found {len(md_files)} markdown files")
    print()

    all_results = []
    total_links = 0
    total_broken = 0

    for file_path in md_files:
        relative_path = os.path.relpath(file_path, base_directory)
        print(f"🔍 Auditing: {relative_path}")

        results = audit_file(file_path, base_directory)
        all_results.append(results)

        total_links += results['total_links']
        total_broken += len(results['broken_links'])

        if results['broken_links']:
            print(f"  ❌ {len(results['broken_links'])} broken links found")
            for link in results['broken_links']:
                print(f"    • [{link['text']}]({link['url']}) - {link['status']}")
        else:
            print(f"  ✅ All {results['total_links']} links working")

        if results['errors']:
            print(f"  ⚠️  Errors: {', '.join(results['errors'])}")

        print()

    # Summary
    print("=" * 60)
    print("📊 AUDIT SUMMARY")
    print("=" * 60)
    print(f"📄 Files audited: {len(md_files)}")
    print(f"🔗 Total links: {total_links}")
    print(f"✅ Working links: {total_links - total_broken}")
    print(f"❌ Broken links: {total_broken}")
    print(f"📈 Success rate: {((total_links - total_broken) / total_links * 100):.1f}%" if total_links > 0 else "N/A")

    # Detailed broken links report
    if total_broken > 0:
        print("\n" + "=" * 60)
        print("🚨 BROKEN LINKS DETAILED REPORT")
        print("=" * 60)

        for results in all_results:
            if results['broken_links']:
                relative_path = os.path.relpath(results['file'], base_directory)
                print(f"\n📄 {relative_path}")
                for link in results['broken_links']:
                    print(f"  ❌ [{link['text']}]({link['url']})")
                    print(f"     Status: {link['status']}")

if __name__ == "__main__":
    main()
