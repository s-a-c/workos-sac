#!/usr/bin/env python3
"""
Comprehensive Chinook Documentation Link Integrity Verification
Performs systematic analysis of all internal markdown links within the chinook guides directory
"""

import os
import re
from pathlib import Path
from typing import List, Dict, Tuple, Set
import json

class ChinookLinkAuditor:
    def __init__(self, base_directory: str):
        self.base_directory = Path(base_directory)
        self.all_files: Set[str] = set()
        self.internal_links: List[Tuple[str, str, str]] = []  # (source_file, link_text, url)
        self.anchor_links: List[Tuple[str, str, str]] = []  # (source_file, link_text, anchor)
        self.broken_links: List[Dict] = []
        self.working_links: List[Dict] = []
        self.link_stats = {
            'total_files': 0,
            'total_links': 0,
            'internal_links': 0,
            'anchor_links': 0,
            'external_links': 0,
            'broken_internal': 0,
            'broken_anchors': 0
        }

    def find_all_markdown_files(self) -> List[Path]:
        """Find all markdown files in the directory tree"""
        md_files = []
        for file_path in self.base_directory.rglob("*.md"):
            if file_path.is_file():
                md_files.append(file_path)
                # Store relative path for link checking
                rel_path = file_path.relative_to(self.base_directory)
                self.all_files.add(str(rel_path))

        self.link_stats['total_files'] = len(md_files)
        return sorted(md_files)

    def extract_links_from_file(self, file_path: Path) -> List[Tuple[str, str]]:
        """Extract all markdown links from a file"""
        links = []
        try:
            with open(file_path, 'r', encoding='utf-8') as f:
                content = f.read()

            # Pattern for markdown links: [text](url)
            link_pattern = r'\[([^\]]*)\]\(([^)]+)\)'
            matches = re.findall(link_pattern, content)

            for text, url in matches:
                links.append((text.strip(), url.strip()))

        except Exception as e:
            print(f"❌ Error reading {file_path}: {e}")

        return links

    def categorize_link(self, url: str) -> str:
        """Categorize link type"""
        if url.startswith('http://') or url.startswith('https://'):
            return 'external'
        elif url.startswith('#'):
            return 'anchor'
        elif url.startswith('mailto:'):
            return 'email'
        else:
            return 'internal'

    def validate_internal_link(self, source_file: Path, url: str) -> Tuple[bool, str]:
        """Validate internal link exists"""
        # Handle anchor links in same file
        if url.startswith('#'):
            return self.validate_anchor_link(source_file, url)

        # Handle relative paths
        if '#' in url:
            file_part, anchor_part = url.split('#', 1)
            url = file_part  # Check file existence first

        # Resolve relative path
        source_dir = source_file.parent
        target_path = source_dir / url

        try:
            # Normalize path and check if it exists
            normalized_path = target_path.resolve()
            relative_to_base = normalized_path.relative_to(self.base_directory.resolve())

            if normalized_path.exists():
                return True, f"File exists: {relative_to_base}"
            else:
                return False, f"File not found: {relative_to_base}"

        except ValueError:
            # Path is outside base directory
            return False, f"Path outside base directory: {target_path}"
        except Exception as e:
            return False, f"Error resolving path: {str(e)}"

    def generate_github_anchor(self, heading_text: str) -> str:
        """
        Generate GitHub-style anchor from heading text using the proven Phase 2 algorithm.

        GitHub anchor generation rules (validated in Phase 2 DRIP):
        1. Convert to lowercase
        2. Replace spaces with hyphens (-)
        3. Remove periods (.)
        4. Convert ampersands to double hyphens (& → --)
        5. Remove special characters except hyphens and alphanumeric
        6. Preserve numbers and letters

        Examples from Phase 2 validation:
        - "1. Overview" → "1-overview"
        - "1.1. Enterprise Features" → "11-enterprise-features"
        - "Setup & Configuration" → "setup--configuration"
        - "SSL/TLS Configuration" → "ssltls-configuration"
        """
        # Start with the heading text
        anchor = heading_text.strip()

        # Convert to lowercase
        anchor = anchor.lower()

        # Handle ampersands with surrounding spaces properly (Phase 2 proven pattern)
        # "word & word" should become "word--word", not "word----word"
        anchor = anchor.replace(' & ', '--')
        anchor = anchor.replace('& ', '--')
        anchor = anchor.replace(' &', '--')
        anchor = anchor.replace('&', '--')

        # Replace remaining spaces with hyphens
        anchor = anchor.replace(' ', '-')

        # Remove periods
        anchor = anchor.replace('.', '')

        # Remove forward slashes
        anchor = anchor.replace('/', '')

        # Remove parentheses
        anchor = anchor.replace('(', '').replace(')', '')

        # Remove other special characters, keeping only alphanumeric and hyphens
        import string
        allowed_chars = string.ascii_lowercase + string.digits + '-'
        anchor = ''.join(c for c in anchor if c in allowed_chars)

        # Clean up multiple consecutive hyphens, but preserve double hyphens from ampersands
        # First, protect double hyphens that came from ampersands
        anchor = anchor.replace('--', '§§')  # Temporary placeholder
        while '--' in anchor:
            anchor = anchor.replace('--', '-')
        # Restore the ampersand-derived double hyphens
        anchor = anchor.replace('§§', '--')

        # Remove leading/trailing hyphens
        anchor = anchor.strip('-')

        return anchor

    def validate_anchor_link(self, source_file: Path, anchor: str) -> Tuple[bool, str]:
        """Validate anchor link points to existing heading using Phase 2 proven algorithm"""
        try:
            with open(source_file, 'r', encoding='utf-8') as f:
                content = f.read()

            # Extract headings using regex
            heading_pattern = r'^#+\s+(.+)$'
            headings = re.findall(heading_pattern, content, re.MULTILINE)

            # Remove the # from the anchor for comparison
            target_anchor = anchor[1:] if anchor.startswith('#') else anchor

            # Check if any heading generates the target anchor
            for heading in headings:
                generated_anchor = self.generate_github_anchor(heading)
                if generated_anchor == target_anchor:
                    return True, f"Anchor found: {heading} → #{generated_anchor}"

            # Debug: Show what anchors were generated for troubleshooting
            generated_anchors = [self.generate_github_anchor(h) for h in headings[:5]]  # First 5 for debugging
            return False, f"Anchor not found: #{target_anchor} (available: {', '.join(f'#{a}' for a in generated_anchors[:3])}...)"

        except Exception as e:
            return False, f"Error checking anchor: {str(e)}"

    def audit_file(self, file_path: Path) -> Dict:
        """Audit a single file for link integrity"""
        rel_path = file_path.relative_to(self.base_directory)
        results = {
            'file': str(rel_path),
            'total_links': 0,
            'internal_links': 0,
            'anchor_links': 0,
            'external_links': 0,
            'broken_links': [],
            'working_links': []
        }

        links = self.extract_links_from_file(file_path)
        results['total_links'] = len(links)

        for link_text, url in links:
            link_type = self.categorize_link(url)

            if link_type == 'internal':
                results['internal_links'] += 1
                is_valid, status = self.validate_internal_link(file_path, url)

            elif link_type == 'anchor':
                results['anchor_links'] += 1
                is_valid, status = self.validate_anchor_link(file_path, url)

            elif link_type == 'external':
                results['external_links'] += 1
                is_valid, status = True, "External link (not validated)"

            else:
                is_valid, status = True, f"Other link type: {link_type}"

            link_info = {
                'text': link_text,
                'url': url,
                'type': link_type,
                'status': status
            }

            if is_valid:
                results['working_links'].append(link_info)
            else:
                results['broken_links'].append(link_info)

        return results

    def run_audit(self) -> Dict:
        """Run complete link integrity audit"""
        print("🔍 Starting Chinook Documentation Link Integrity Verification")
        print(f"📁 Base directory: {self.base_directory}")
        print("=" * 80)

        # Find all markdown files
        md_files = self.find_all_markdown_files()
        print(f"📄 Found {len(md_files)} markdown files")
        print()

        all_results = []
        total_broken = 0

        for file_path in md_files:
            rel_path = file_path.relative_to(self.base_directory)
            print(f"🔍 Auditing: {rel_path}")

            results = self.audit_file(file_path)
            all_results.append(results)

            self.link_stats['total_links'] += results['total_links']
            self.link_stats['internal_links'] += results['internal_links']
            self.link_stats['anchor_links'] += results['anchor_links']
            self.link_stats['external_links'] += results['external_links']

            broken_count = len(results['broken_links'])
            total_broken += broken_count

            if broken_count > 0:
                print(f"  ❌ {broken_count} broken links found")
                for link in results['broken_links']:
                    print(f"    • [{link['text']}]({link['url']}) - {link['status']}")
                    if link['type'] == 'internal':
                        self.link_stats['broken_internal'] += 1
                    elif link['type'] == 'anchor':
                        self.link_stats['broken_anchors'] += 1
            else:
                print(f"  ✅ All {results['total_links']} links working")

            print()

        # Generate summary
        print("📊 AUDIT SUMMARY")
        print("=" * 80)
        print(f"Total Files Audited: {self.link_stats['total_files']}")
        print(f"Total Links Found: {self.link_stats['total_links']}")
        print(f"  - Internal Links: {self.link_stats['internal_links']}")
        print(f"  - Anchor Links: {self.link_stats['anchor_links']}")
        print(f"  - External Links: {self.link_stats['external_links']}")
        print(f"Broken Links: {total_broken}")
        print(f"  - Broken Internal: {self.link_stats['broken_internal']}")
        print(f"  - Broken Anchors: {self.link_stats['broken_anchors']}")

        if total_broken == 0:
            print("\n🎉 ALL LINKS ARE WORKING! Documentation has perfect link integrity.")
        else:
            print(f"\n⚠️  {total_broken} broken links need attention.")

        return {
            'summary': self.link_stats,
            'results': all_results,
            'total_broken': total_broken
        }

def main():
    base_dir = "/Users/s-a-c/Herd/workos-sac/.ai/guides/chinook/"
    auditor = ChinookLinkAuditor(base_dir)
    audit_results = auditor.run_audit()

    # Save results to JSON file
    with open('chinook_link_audit_results.json', 'w') as f:
        json.dump(audit_results, f, indent=2)

    print(f"\n📄 Detailed results saved to: chinook_link_audit_results.json")

if __name__ == "__main__":
    main()
