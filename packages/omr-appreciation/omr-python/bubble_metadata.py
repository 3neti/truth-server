#!/usr/bin/env python3
"""
Bubble metadata loading and lookup.

Supports loading metadata from election configs (mapping.yaml + election.json)
or falling back to parsing for backward compatibility.
"""

import json
import yaml
from pathlib import Path
from typing import Dict, Optional


class BubbleMetadata:
    """
    Load and provide bubble metadata from election configs.
    
    Supports both simple bubble IDs (A1, B1) and verbose IDs (PRESIDENT_LD_001).
    """
    
    def __init__(self, config_path: Optional[str] = None):
        """
        Initialize with optional config path.
        
        Args:
            config_path: Path to directory containing election.json and mapping.yaml
                        If None, metadata lookups will return None (backward compatible)
        """
        self.metadata: Dict[str, Dict] = {}
        self.available = False
        
        if config_path:
            self._load_from_configs(config_path)
    
    def _load_from_configs(self, config_path: str):
        """Load metadata from election configs."""
        try:
            config_dir = Path(config_path)
            
            # Load election.json
            election_file = config_dir / 'election.json'
            if not election_file.exists():
                return
            
            with open(election_file) as f:
                election = json.load(f)
            
            # Load mapping.yaml
            mapping_file = config_dir / 'mapping.yaml'
            if not mapping_file.exists():
                return
            
            with open(mapping_file) as f:
                mapping = yaml.safe_load(f)
            
            # Build metadata
            for mark in mapping.get('marks', []):
                bubble_id = mark['key']
                candidate_code = mark['value']
                
                # Find position and candidate details
                position_code = self._find_position(candidate_code, election)
                candidate = self._find_candidate(candidate_code, position_code, election)
                
                if position_code and candidate:
                    self.metadata[bubble_id] = {
                        'bubble_id': bubble_id,
                        'candidate_code': candidate_code,
                        'position_code': position_code,
                        'candidate_name': candidate['name'],
                        'candidate_alias': candidate.get('alias', ''),
                    }
            
            self.available = len(self.metadata) > 0
            
        except Exception as e:
            print(f"Warning: Could not load bubble metadata: {e}")
            self.available = False
    
    def _find_position(self, candidate_code: str, election: dict) -> Optional[str]:
        """Find position code for a candidate."""
        for position_code, candidates in election.get('candidates', {}).items():
            for candidate in candidates:
                if candidate.get('code') == candidate_code:
                    return position_code
        return None
    
    def _find_candidate(self, candidate_code: str, position_code: str, election: dict) -> Optional[dict]:
        """Find candidate details."""
        for candidate in election.get('candidates', {}).get(position_code, []):
            if candidate.get('code') == candidate_code:
                return candidate
        return None
    
    def get(self, bubble_id: str) -> Optional[Dict]:
        """
        Get metadata for a bubble ID.
        
        Returns None if metadata not available (backward compatible).
        """
        return self.metadata.get(bubble_id)
    
    def is_simple_id(self, bubble_id: str) -> bool:
        """
        Check if bubble ID is simple format (no underscores).
        
        Simple: A1, B1, C23
        Verbose: PRESIDENT_LD_001, SENATOR_ES_002
        """
        return '_' not in bubble_id


def load_bubble_metadata(config_path: Optional[str] = None) -> BubbleMetadata:
    """
    Load bubble metadata from configs.
    
    Returns BubbleMetadata instance (may be empty if configs not available).
    """
    return BubbleMetadata(config_path)
