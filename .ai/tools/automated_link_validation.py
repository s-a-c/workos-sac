#!/usr/bin/env python3
"""
Automated Link Validation System for Chinook Documentation
Provides CI/CD integration and continuous monitoring capabilities
"""

import os
import sys
import json
import time
import argparse
from pathlib import Path
from typing import Dict, List, Tuple, Optional
from dataclasses import dataclass, asdict
from datetime import datetime, timedelta

# Import the existing auditor
sys.path.append(str(Path(__file__).parent))
from chinook_link_integrity_audit import ChinookLinkAuditor


@dataclass
class ValidationConfig:
    """Configuration for automated validation"""
    base_directory: str
    max_broken_links: int = 50
    critical_files: List[str] = None
    notification_webhook: Optional[str] = None
    report_directory: str = ".ai/reports/automated"
    history_retention_days: int = 30

    def __post_init__(self):
        if self.critical_files is None:
            self.critical_files = [
                "000-chinook-index.md",
                "050-chinook-advanced-features-guide.md",
                "060-chinook-media-library-guide.md",
                "070-chinook-hierarchy-comparison-guide.md",
                "filament/setup/000-index.md",
                "filament/resources/000-index.md",
                "packages/000-packages-index.md",
                "testing/000-testing-index.md"
            ]


@dataclass
class ValidationResult:
    """Result of validation run"""
    timestamp: str
    total_files: int
    total_links: int
    broken_links: int
    success_rate: float
    critical_files_status: Dict[str, int]
    new_issues: List[Dict]
    resolved_issues: List[Dict]
    status: str  # "PASS", "WARN", "FAIL"
    execution_time: float


class AutomatedValidator:
    """Automated validation system with CI/CD integration"""

    def __init__(self, config: ValidationConfig):
        self.config = config
        self.auditor = ChinookLinkAuditor(config.base_directory)
        self.report_dir = Path(config.report_directory)
        self.report_dir.mkdir(parents=True, exist_ok=True)

    def run_validation(self) -> ValidationResult:
        """Run complete validation and return results"""
        start_time = time.time()
        timestamp = datetime.now().isoformat()

        print(f"🔍 Starting automated validation at {timestamp}")

        # Run full audit
        results = {}
        for md_file in self.auditor.base_directory.glob("**/*.md"):
            if md_file.is_file():
                relative_path = md_file.relative_to(self.auditor.base_directory)
                result = self.auditor.audit_file(md_file)
                results[str(relative_path)] = result

        # Analyze critical files
        critical_status = self._analyze_critical_files(results)

        # Compare with previous run
        previous_result = self._load_previous_result()
        new_issues, resolved_issues = self._compare_results(results, previous_result)

        # Determine status
        status = self._determine_status(results, critical_status)

        # Calculate metrics
        total_links = sum(r['total_links'] for r in results.values())
        total_broken = sum(len(r['broken_links']) for r in results.values())
        success_rate = ((total_links - total_broken) / total_links * 100) if total_links > 0 else 0

        execution_time = time.time() - start_time

        result = ValidationResult(
            timestamp=timestamp,
            total_files=len(results),
            total_links=total_links,
            broken_links=total_broken,
            success_rate=success_rate,
            critical_files_status=critical_status,
            new_issues=new_issues,
            resolved_issues=resolved_issues,
            status=status,
            execution_time=execution_time
        )

        # Save results
        self._save_result(result)

        # Generate reports
        self._generate_reports(result, results)

        # Send notifications if configured
        if self.config.notification_webhook:
            self._send_notification(result)

        # Cleanup old reports
        self._cleanup_old_reports()

        return result

    def _analyze_critical_files(self, results: Dict) -> Dict[str, int]:
        """Analyze status of critical files"""
        critical_status = {}

        for file_path in self.config.critical_files:
            if file_path in results:
                broken_count = len(results[file_path]['broken_links'])
                critical_status[file_path] = broken_count
            else:
                critical_status[file_path] = -1  # File not found

        return critical_status

    def _load_previous_result(self) -> Optional[Dict]:
        """Load previous validation result for comparison"""
        history_file = self.report_dir / "validation_history.json"

        if not history_file.exists():
            return None

        try:
            with open(history_file, 'r') as f:
                history = json.load(f)
                return history[-1] if history else None
        except (json.JSONDecodeError, IndexError):
            return None

    def _compare_results(self, current: Dict, previous: Optional[Dict]) -> Tuple[List[Dict], List[Dict]]:
        """Compare current results with previous to find new/resolved issues"""
        if not previous:
            return [], []

        new_issues = []
        resolved_issues = []

        # Get previous broken links
        previous_broken = set()
        if 'detailed_results' in previous:
            for file_result in previous['detailed_results'].values():
                for broken_link in file_result.get('broken_links', []):
                    previous_broken.add(f"{file_result['file']}:{broken_link['url']}")

        # Get current broken links
        current_broken = set()
        for file_result in current.values():
            for broken_link in file_result['broken_links']:
                link_id = f"{file_result['file']}:{broken_link['url']}"
                current_broken.add(link_id)

                # Check if this is a new issue
                if link_id not in previous_broken:
                    new_issues.append({
                        'file': file_result['file'],
                        'link': broken_link['url'],
                        'text': broken_link['text'],
                        'status': broken_link['status']
                    })

        # Find resolved issues
        for prev_link in previous_broken:
            if prev_link not in current_broken:
                file_path, url = prev_link.split(':', 1)
                resolved_issues.append({
                    'file': file_path,
                    'link': url
                })

        return new_issues, resolved_issues

    def _determine_status(self, results: Dict, critical_status: Dict[str, int]) -> str:
        """Determine overall validation status"""
        total_broken = sum(len(r['broken_links']) for r in results.values())

        # Check for critical file failures
        critical_failures = sum(1 for count in critical_status.values() if count > 5)

        if total_broken > self.config.max_broken_links or critical_failures > 2:
            return "FAIL"
        elif total_broken > self.config.max_broken_links * 0.8 or critical_failures > 0:
            return "WARN"
        else:
            return "PASS"

    def _save_result(self, result: ValidationResult):
        """Save validation result to history"""
        history_file = self.report_dir / "validation_history.json"

        # Load existing history
        history = []
        if history_file.exists():
            try:
                with open(history_file, 'r') as f:
                    history = json.load(f)
            except json.JSONDecodeError:
                history = []

        # Add new result
        history.append(asdict(result))

        # Keep only recent results
        cutoff_date = datetime.now() - timedelta(days=self.config.history_retention_days)
        history = [
            r for r in history
            if datetime.fromisoformat(r['timestamp']) > cutoff_date
        ]

        # Save updated history
        with open(history_file, 'w') as f:
            json.dump(history, f, indent=2)

    def _generate_reports(self, result: ValidationResult, detailed_results: Dict):
        """Generate validation reports"""
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")

        # Generate summary report
        summary_file = self.report_dir / f"validation_summary_{timestamp}.md"
        self._generate_summary_report(result, summary_file)

        # Generate detailed report
        detailed_file = self.report_dir / f"validation_detailed_{timestamp}.json"
        with open(detailed_file, 'w') as f:
            json.dump({
                'metadata': asdict(result),
                'detailed_results': detailed_results
            }, f, indent=2)

        # Generate trend report
        self._generate_trend_report()

    def _generate_summary_report(self, result: ValidationResult, output_file: Path):
        """Generate markdown summary report"""
        status_emoji = {"PASS": "✅", "WARN": "⚠️", "FAIL": "❌"}

        content = f"""# Automated Link Validation Report

**Status:** {status_emoji.get(result.status, '❓')} {result.status}
**Timestamp:** {result.timestamp}
**Execution Time:** {result.execution_time:.2f} seconds

## Summary

- **Total Files:** {result.total_files}
- **Total Links:** {result.total_links}
- **Broken Links:** {result.broken_links}
- **Success Rate:** {result.success_rate:.1f}%

## Critical Files Status

| File | Broken Links | Status |
|------|--------------|--------|
"""

        for file_path, broken_count in result.critical_files_status.items():
            if broken_count == -1:
                status = "❌ Missing"
            elif broken_count == 0:
                status = "✅ Perfect"
            elif broken_count <= 2:
                status = "⚠️ Minor Issues"
            else:
                status = "❌ Major Issues"

            content += f"| {file_path} | {broken_count if broken_count >= 0 else 'N/A'} | {status} |\n"

        if result.new_issues:
            content += f"\n## New Issues ({len(result.new_issues)})\n\n"
            for issue in result.new_issues[:10]:  # Show first 10
                content += f"- **{issue['file']}:** {issue['text']} → {issue['link']}\n"

            if len(result.new_issues) > 10:
                content += f"\n*... and {len(result.new_issues) - 10} more*\n"

        if result.resolved_issues:
            content += f"\n## Resolved Issues ({len(result.resolved_issues)})\n\n"
            for issue in result.resolved_issues[:10]:  # Show first 10
                content += f"- **{issue['file']}:** {issue['link']}\n"

            if len(result.resolved_issues) > 10:
                content += f"\n*... and {len(result.resolved_issues) - 10} more*\n"

        content += f"""
## Recommendations

"""

        if result.status == "FAIL":
            content += "🚨 **Immediate Action Required:** High number of broken links detected.\n"
        elif result.status == "WARN":
            content += "⚠️ **Attention Needed:** Increasing number of broken links.\n"
        else:
            content += "✅ **Good Status:** Documentation links are healthy.\n"

        with open(output_file, 'w') as f:
            f.write(content)

    def _generate_trend_report(self):
        """Generate trend analysis report"""
        history_file = self.report_dir / "validation_history.json"

        if not history_file.exists():
            return

        with open(history_file, 'r') as f:
            history = json.load(f)

        if len(history) < 2:
            return

        # Generate trend analysis
        trend_file = self.report_dir / "validation_trends.md"

        recent_results = history[-10:]  # Last 10 results

        content = "# Link Validation Trends\n\n"
        content += "| Date | Files | Links | Broken | Success Rate | Status |\n"
        content += "|------|-------|-------|--------|--------------|--------|\n"

        for result in recent_results:
            date = datetime.fromisoformat(result['timestamp']).strftime('%Y-%m-%d %H:%M')
            content += f"| {date} | {result['total_files']} | {result['total_links']} | {result['broken_links']} | {result['success_rate']:.1f}% | {result['status']} |\n"

        with open(trend_file, 'w') as f:
            f.write(content)

    def _send_notification(self, result: ValidationResult):
        """Send notification via webhook"""
        import requests

        status_emoji = {"PASS": "✅", "WARN": "⚠️", "FAIL": "❌"}

        message = {
            "text": f"{status_emoji.get(result.status, '❓')} Link Validation: {result.status}",
            "attachments": [{
                "color": {"PASS": "good", "WARN": "warning", "FAIL": "danger"}.get(result.status, "warning"),
                "fields": [
                    {"title": "Success Rate", "value": f"{result.success_rate:.1f}%", "short": True},
                    {"title": "Broken Links", "value": str(result.broken_links), "short": True},
                    {"title": "New Issues", "value": str(len(result.new_issues)), "short": True},
                    {"title": "Resolved Issues", "value": str(len(result.resolved_issues)), "short": True}
                ]
            }]
        }

        try:
            requests.post(self.config.notification_webhook, json=message, timeout=10)
        except requests.RequestException as e:
            print(f"Failed to send notification: {e}")

    def _cleanup_old_reports(self):
        """Clean up old report files"""
        cutoff_date = datetime.now() - timedelta(days=self.config.history_retention_days)

        for file_path in self.report_dir.glob("validation_*"):
            if file_path.stat().st_mtime < cutoff_date.timestamp():
                file_path.unlink()


def main():
    """Main entry point for automated validation"""
    parser = argparse.ArgumentParser(description="Automated Link Validation for Chinook Documentation")
    parser.add_argument("--config", help="Configuration file path")
    parser.add_argument("--base-dir", default=".ai/guides/chinook", help="Base directory for documentation")
    parser.add_argument("--max-broken", type=int, default=50, help="Maximum allowed broken links")
    parser.add_argument("--webhook", help="Notification webhook URL")
    parser.add_argument("--ci", action="store_true", help="CI mode - exit with error code on failure")

    args = parser.parse_args()

    # Create configuration
    config = ValidationConfig(
        base_directory=args.base_dir,
        max_broken_links=args.max_broken,
        notification_webhook=args.webhook
    )

    # Run validation
    validator = AutomatedValidator(config)
    result = validator.run_validation()

    # Print summary
    print(f"\n📊 Validation Complete: {result.status}")
    print(f"Success Rate: {result.success_rate:.1f}%")
    print(f"Broken Links: {result.broken_links}")
    print(f"Execution Time: {result.execution_time:.2f}s")

    # Exit with appropriate code for CI
    if args.ci:
        exit_code = {"PASS": 0, "WARN": 1, "FAIL": 2}.get(result.status, 1)
        sys.exit(exit_code)


if __name__ == "__main__":
    main()
