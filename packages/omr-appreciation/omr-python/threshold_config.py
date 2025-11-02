#!/usr/bin/env python3
"""
Load OMR threshold configuration from Laravel config file.

This module provides a way to access Laravel's configuration from Python scripts,
ensuring consistent threshold values across PHP and Python components.
"""

import os
import sys
import json
import subprocess
from typing import Dict, Any, Optional


class ThresholdConfig:
    """
    Load and cache OMR threshold configuration from Laravel.
    """
    
    def __init__(self, laravel_root: Optional[str] = None):
        """
        Initialize threshold config loader.
        
        Args:
            laravel_root: Path to Laravel project root. If None, attempts to detect it.
        """
        self.laravel_root = laravel_root or self._find_laravel_root()
        self._config_cache = None
    
    def _find_laravel_root(self) -> str:
        """Find Laravel project root by walking up from this file."""
        current = os.path.dirname(os.path.abspath(__file__))
        while current != '/':
            if os.path.exists(os.path.join(current, 'artisan')):
                return current
            current = os.path.dirname(current)
        
        # Fallback: assume we're in packages/omr-appreciation/omr-python/
        script_dir = os.path.dirname(os.path.abspath(__file__))
        return os.path.abspath(os.path.join(script_dir, '../../..'))
    
    def load(self) -> Dict[str, Any]:
        """
        Load threshold configuration from Laravel.
        
        Returns:
            Dictionary of threshold configuration values.
        """
        if self._config_cache is not None:
            return self._config_cache
        
        # Use Laravel's artisan tinker to read config
        artisan_path = os.path.join(self.laravel_root, 'artisan')
        
        if not os.path.exists(artisan_path):
            print(f"Warning: Laravel artisan not found at {artisan_path}", file=sys.stderr)
            return self._get_defaults()
        
        try:
            # Execute PHP to read config as JSON
            result = subprocess.run(
                ['php', artisan_path, 'tinker', '--execute=echo json_encode(config("omr-thresholds"));'],
                cwd=self.laravel_root,
                capture_output=True,
                text=True,
                timeout=5
            )
            
            if result.returncode == 0:
                # Extract JSON from output (skip tinker preamble)
                output_lines = result.stdout.strip().split('\n')
                json_line = output_lines[-1]  # Last line should be the JSON
                
                try:
                    config = json.loads(json_line)
                    self._config_cache = config
                    return config
                except json.JSONDecodeError:
                    print(f"Warning: Could not parse config JSON: {json_line}", file=sys.stderr)
            else:
                print(f"Warning: Laravel config read failed: {result.stderr}", file=sys.stderr)
        
        except subprocess.TimeoutExpired:
            print("Warning: Laravel config read timed out", file=sys.stderr)
        except Exception as e:
            print(f"Warning: Error reading Laravel config: {e}", file=sys.stderr)
        
        return self._get_defaults()
    
    def _get_defaults(self) -> Dict[str, Any]:
        """Return default threshold values if Laravel config unavailable."""
        return {
            'detection_threshold': 0.3,
            'classification': {
                'valid_mark': 0.95,
                'ambiguous_min': 0.15,
                'ambiguous_max': 0.45,
                'faint_mark': 0.16,
                'overfilled': 0.7,
            },
            'confidence': {
                'reference': 0.3,
                'perfect_fill': 0.5,
                'noise_threshold': 0.15,
                'low_confidence': 0.5,
            },
            'quality': {
                'min_uniformity': 0.4,
                'high_std_dev': 60,
            },
        }
    
    def get_detection_threshold(self) -> float:
        """Get the primary detection threshold."""
        config = self.load()
        return float(config.get('detection_threshold', 0.3))
    
    def get_classification(self) -> Dict[str, float]:
        """Get classification thresholds."""
        config = self.load()
        return config.get('classification', {})
    
    def get_confidence(self) -> Dict[str, float]:
        """Get confidence calculation thresholds."""
        config = self.load()
        return config.get('confidence', {})
    
    def get_quality(self) -> Dict[str, float]:
        """Get quality metric thresholds."""
        config = self.load()
        return config.get('quality', {})


# Global singleton instance
_threshold_config = None


def get_threshold_config(laravel_root: Optional[str] = None) -> ThresholdConfig:
    """
    Get or create the global ThresholdConfig instance.
    
    Args:
        laravel_root: Path to Laravel project root (only used on first call).
    
    Returns:
        ThresholdConfig instance.
    """
    global _threshold_config
    if _threshold_config is None:
        _threshold_config = ThresholdConfig(laravel_root)
    return _threshold_config


# Convenience functions for direct access
def get_detection_threshold() -> float:
    """Get the primary detection threshold from config."""
    return get_threshold_config().get_detection_threshold()


def get_classification_thresholds() -> Dict[str, float]:
    """Get classification thresholds from config."""
    return get_threshold_config().get_classification()


def get_confidence_thresholds() -> Dict[str, float]:
    """Get confidence calculation thresholds from config."""
    return get_threshold_config().get_confidence()


def get_quality_thresholds() -> Dict[str, float]:
    """Get quality metric thresholds from config."""
    return get_threshold_config().get_quality()
