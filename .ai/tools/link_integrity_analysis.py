#!/usr/bin/env python3
"""
Chinook Documentation Link Integrity Analysis & Remediation Plan
Analyzes the audit results and creates a comprehensive remediation strategy
"""

import json
from collections import defaultdict
from pathlib import Path

class LinkIntegrityAnalyzer:
    def __init__(self, audit_results_file: str):
        with open(audit_results_file, 'r') as f:
            self.audit_data = json.load(f)
        
        self.broken_files = defaultdict(list)
        self.broken_anchors = defaultdict(list)
        self.external_references = []
        self.missing_files = set()
        
    def analyze_issues(self):
        """Categorize all broken links by type and severity"""
        print("🔍 ANALYZING LINK INTEGRITY ISSUES")
        print("=" * 80)
        
        for result in self.audit_data['results']:
            file_path = result['file']
            
            for broken_link in result['broken_links']:
                link_type = broken_link['type']
                url = broken_link['url']
                status = broken_link['status']
                
                if link_type == 'internal':
                    if 'File not found' in status:
                        # Extract the missing file path
                        missing_file = url.split('#')[0]  # Remove anchor if present
                        self.missing_files.add(missing_file)
                        self.broken_files[file_path].append(broken_link)
                    elif 'Path outside base directory' in status:
                        self.external_references.append({
                            'source_file': file_path,
                            'link': broken_link
                        })
                
                elif link_type == 'anchor':
                    self.broken_anchors[file_path].append(broken_link)
        
        self.print_analysis_summary()
        
    def print_analysis_summary(self):
        """Print comprehensive analysis summary"""
        print(f"📊 ISSUE CATEGORIZATION")
        print("-" * 40)
        print(f"Missing Files: {len(self.missing_files)}")
        print(f"Files with Broken Internal Links: {len(self.broken_files)}")
        print(f"Files with Broken Anchors: {len(self.broken_anchors)}")
        print(f"External Directory References: {len(self.external_references)}")
        print()
        
        # Missing files analysis
        if self.missing_files:
            print("🚨 MISSING FILES (HIGH PRIORITY)")
            print("-" * 40)
            for missing_file in sorted(self.missing_files):
                print(f"  • {missing_file}")
            print()
        
        # External references analysis
        if self.external_references:
            print("⚠️  EXTERNAL DIRECTORY REFERENCES (MEDIUM PRIORITY)")
            print("-" * 40)
            for ref in self.external_references:
                print(f"  • {ref['source_file']}: {ref['link']['url']}")
            print()
        
        # Anchor issues by file
        print("🔗 ANCHOR LINK ISSUES BY FILE")
        print("-" * 40)
        for file_path, anchors in sorted(self.broken_anchors.items()):
            if anchors:
                print(f"  📄 {file_path} ({len(anchors)} broken anchors)")
                for anchor in anchors[:3]:  # Show first 3
                    print(f"    • {anchor['url']}")
                if len(anchors) > 3:
                    print(f"    ... and {len(anchors) - 3} more")
                print()
    
    def generate_remediation_plan(self):
        """Generate comprehensive remediation plan"""
        print("🛠️  COMPREHENSIVE REMEDIATION PLAN")
        print("=" * 80)
        
        print("PHASE 1: CRITICAL FIXES (Immediate Action Required)")
        print("-" * 50)
        
        # 1. External directory references
        print("1.1. Remove External Directory References")
        print("   These links point outside the chinook directory and should be removed or updated:")
        for ref in self.external_references:
            print(f"   • {ref['source_file']}: {ref['link']['url']}")
        print()
        
        # 2. Missing files that are referenced multiple times
        file_reference_count = defaultdict(int)
        for result in self.audit_data['results']:
            for broken_link in result['broken_links']:
                if broken_link['type'] == 'internal' and 'File not found' in broken_link['status']:
                    missing_file = broken_link['url'].split('#')[0]
                    file_reference_count[missing_file] += 1
        
        high_priority_missing = {f: count for f, count in file_reference_count.items() if count >= 3}
        
        print("1.2. Create Missing Files (High Priority - Referenced 3+ times)")
        for missing_file, count in sorted(high_priority_missing.items(), key=lambda x: x[1], reverse=True):
            print(f"   • {missing_file} (referenced {count} times)")
        print()
        
        print("PHASE 2: ANCHOR LINK FIXES")
        print("-" * 50)
        
        # Files with most anchor issues
        anchor_priority = sorted(self.broken_anchors.items(), key=lambda x: len(x[1]), reverse=True)
        
        print("2.1. Files Requiring Heading Structure Updates (Top 10)")
        for file_path, anchors in anchor_priority[:10]:
            print(f"   • {file_path}: {len(anchors)} broken anchors")
        print()
        
        print("PHASE 3: REMAINING MISSING FILES")
        print("-" * 50)
        
        medium_priority_missing = {f: count for f, count in file_reference_count.items() if count < 3}
        
        print("3.1. Create Remaining Missing Files")
        for missing_file, count in sorted(medium_priority_missing.items()):
            print(f"   • {missing_file} (referenced {count} times)")
        print()
        
        print("PHASE 4: DOCUMENTATION STANDARDS COMPLIANCE")
        print("-" * 50)
        print("4.1. Verify all new content follows:")
        print("   • WCAG 2.1 AA accessibility standards")
        print("   • Laravel 12 modern syntax")
        print("   • Mermaid v10.6+ diagram syntax")
        print("   • Project documentation conventions")
        print()
        
        print("PHASE 5: QUALITY ASSURANCE")
        print("-" * 50)
        print("5.1. Re-run link integrity verification")
        print("5.2. Validate all fixes maintain navigation flow")
        print("5.3. Ensure cross-references are bidirectional")
        print("5.4. Test accessibility compliance")
        print()
    
    def generate_fix_commands(self):
        """Generate specific fix commands for immediate issues"""
        print("🔧 IMMEDIATE FIX COMMANDS")
        print("=" * 80)
        
        print("# Remove external directory references from 000-chinook-index.md")
        print("# These links point outside the chinook directory:")
        for ref in self.external_references:
            if ref['source_file'] == '000-chinook-index.md':
                print(f"# Remove: {ref['link']['url']}")
        print()
        
        print("# Create missing high-priority files:")
        file_reference_count = defaultdict(int)
        for result in self.audit_data['results']:
            for broken_link in result['broken_links']:
                if broken_link['type'] == 'internal' and 'File not found' in broken_link['status']:
                    missing_file = broken_link['url'].split('#')[0]
                    file_reference_count[missing_file] += 1
        
        high_priority_missing = {f: count for f, count in file_reference_count.items() if count >= 3}
        
        for missing_file in sorted(high_priority_missing.keys()):
            print(f"touch .ai/guides/chinook/{missing_file}")
        print()
        
        print("# Files requiring immediate attention (most broken anchors):")
        anchor_priority = sorted(self.broken_anchors.items(), key=lambda x: len(x[1]), reverse=True)
        for file_path, anchors in anchor_priority[:5]:
            print(f"# {file_path}: {len(anchors)} broken anchors need heading structure review")

def main():
    analyzer = LinkIntegrityAnalyzer('chinook_link_audit_results.json')
    analyzer.analyze_issues()
    analyzer.generate_remediation_plan()
    analyzer.generate_fix_commands()

if __name__ == "__main__":
    main()
