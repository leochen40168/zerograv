"""Make outreach/src importable as flat modules during pytest."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent / "src"))
