#!/usr/bin/env python3
"""
Test Phase 4 Enhanced AR Features

Tests vote accumulator, session management, validation, and audio feedback.
"""
import sys
import os
from pathlib import Path

# Add parent to path
sys.path.insert(0, str(Path(__file__).parent.parent / 'omr-python'))

import json
import tempfile
import shutil
from collections import deque
import traceback

from appreciate_live import (
    VoteAccumulator,
    BallotSession,
    ContestValidator,
    AudioFeedback
)


class TestVoteAccumulator:
    """Test vote accumulator for frame stability."""
    
    def test_initialization(self):
        """Test accumulator initializes correctly."""
        acc = VoteAccumulator(window_size=10, threshold=8)
        assert acc.window_size == 10
        assert acc.threshold == 8
        assert len(acc.stable_votes) == 0
    
    def test_single_detection_not_stable(self):
        """Test single detection doesn't trigger stable vote."""
        acc = VoteAccumulator(window_size=10, threshold=8)
        
        results = {
            'PRESIDENT_LD_001': {'filled': True, 'fill_ratio': 0.98}
        }
        
        stable = acc.update(results)
        
        # Should not be stable yet (only 1 of 8 needed)
        assert stable.get('PRESIDENT_LD_001', False) == False
    
    def test_consistent_detection_becomes_stable(self):
        """Test consistent detection over threshold becomes stable."""
        acc = VoteAccumulator(window_size=10, threshold=8)
        
        # Feed 8 consistent detections
        for _ in range(8):
            results = {
                'PRESIDENT_LD_001': {'filled': True, 'fill_ratio': 0.98}
            }
            stable = acc.update(results)
        
        # Should be stable now
        assert stable['PRESIDENT_LD_001'] == True
    
    def test_flickering_not_stable(self):
        """Test flickering detection doesn't become stable."""
        acc = VoteAccumulator(window_size=10, threshold=8)
        
        # Alternate between filled and not filled
        for i in range(10):
            results = {
                'PRESIDENT_LD_001': {'filled': i % 2 == 0, 'fill_ratio': 0.98}
            }
            stable = acc.update(results)
        
        # Should not be stable (only 5 of 10 frames)
        assert stable.get('PRESIDENT_LD_001', False) == False
    
    def test_vote_change_callback(self):
        """Test vote change callbacks are triggered."""
        acc = VoteAccumulator(window_size=10, threshold=8)
        
        changes = []
        
        def callback(bubble_id, is_filled):
            changes.append((bubble_id, is_filled))
        
        acc.on_vote_change(callback)
        
        # Feed 8 consistent detections to trigger stable vote
        for _ in range(8):
            results = {
                'PRESIDENT_LD_001': {'filled': True, 'fill_ratio': 0.98}
            }
            acc.update(results)
        
        # Should have triggered callback
        assert len(changes) == 1
        assert changes[0] == ('PRESIDENT_LD_001', True)
    
    def test_reset_clears_history(self):
        """Test reset clears all history and stable votes."""
        acc = VoteAccumulator(window_size=10, threshold=8)
        
        # Make a vote stable
        for _ in range(8):
            results = {'PRESIDENT_LD_001': {'filled': True, 'fill_ratio': 0.98}}
            acc.update(results)
        
        assert 'PRESIDENT_LD_001' in acc.stable_votes
        
        # Reset
        acc.reset()
        
        assert len(acc.stable_votes) == 0
        assert len(acc.history) == 0


class TestBallotSession:
    """Test ballot session management."""
    
    def test_session_creation(self):
        """Test session creates directory and metadata."""
        with tempfile.TemporaryDirectory() as tmpdir:
            session_dir = Path(tmpdir)
            session = BallotSession('BAL-001', session_dir)
            
            assert session.document_id == 'BAL-001'
            assert session.status == 'active'
            assert session.frames_processed == 0
            assert session.session_path.exists()
            assert (session.session_path / 'session.json').exists()
    
    def test_update_votes(self):
        """Test updating vote state."""
        with tempfile.TemporaryDirectory() as tmpdir:
            session_dir = Path(tmpdir)
            session = BallotSession('BAL-001', session_dir)
            
            votes = {
                'PRESIDENT_LD_001': True,
                'VICE-PRESIDENT_VD_002': True
            }
            
            session.update_votes(votes)
            
            assert session.frames_processed == 1
            assert session.votes == votes
    
    def test_freeze_unfreeze(self):
        """Test session freeze/unfreeze."""
        with tempfile.TemporaryDirectory() as tmpdir:
            session_dir = Path(tmpdir)
            session = BallotSession('BAL-001', session_dir)
            
            session.freeze()
            assert session.status == 'frozen'
            
            session.unfreeze()
            assert session.status == 'active'
    
    def test_finalize_generates_ballot_string(self):
        """Test finalization generates correct ballot string."""
        with tempfile.TemporaryDirectory() as tmpdir:
            session_dir = Path(tmpdir)
            session = BallotSession('BAL-001', session_dir)
            
            votes = {
                'PRESIDENT_LD_001': True,
                'VICE-PRESIDENT_VD_002': True,
                'SENATOR_JD_001': True,
                'SENATOR_ES_002': True,
                'SENATOR_MF_003': False  # Not filled
            }
            
            session.update_votes(votes)
            ballot_string = session.finalize()
            
            # Should format as: BAL-001|POSITION:CODE1,CODE2;POSITION:CODE3
            assert ballot_string.startswith('BAL-001|')
            assert 'PRESIDENT:LD_001' in ballot_string
            assert 'VICE-PRESIDENT:VD_002' in ballot_string
            assert 'SENATOR:ES_002,JD_001' in ballot_string or 'SENATOR:JD_001,ES_002' in ballot_string
            assert session.status == 'finalized'
            
            # Check ballot.txt was written
            assert (session.session_path / 'ballot.txt').exists()
    
    def test_validation_error_tracking(self):
        """Test validation error tracking."""
        with tempfile.TemporaryDirectory() as tmpdir:
            session_dir = Path(tmpdir)
            session = BallotSession('BAL-001', session_dir)
            
            session.add_validation_error('PRESIDENT', 'Overvote', 2, 1)
            
            assert len(session.validation_errors) == 1
            assert session.validation_errors[0]['position'] == 'PRESIDENT'
            assert session.validation_errors[0]['vote_count'] == 2
            assert session.validation_errors[0]['max_selections'] == 1
    
    def test_session_persistence(self):
        """Test session can be saved and loaded."""
        with tempfile.TemporaryDirectory() as tmpdir:
            session_dir = Path(tmpdir)
            
            # Create and populate session
            session = BallotSession('BAL-001', session_dir)
            votes = {'PRESIDENT_LD_001': True}
            session.update_votes(votes)
            session.freeze()
            
            session_path = session.session_path
            
            # Load session
            loaded = BallotSession.load(session_path)
            
            assert loaded.document_id == 'BAL-001'
            assert loaded.status == 'frozen'
            assert loaded.votes == votes
            assert loaded.frames_processed == 1


class TestContestValidator:
    """Test multi-contest validation."""
    
    def test_initialization_from_questionnaire(self):
        """Test validator loads rules from questionnaire data."""
        questionnaire = {
            'positions': [
                {'code': 'PRESIDENT', 'max_selections': 1},
                {'code': 'SENATOR', 'max_selections': 12},
                {'code': 'REPRESENTATIVE-PARTY-LIST', 'max_selections': 1}
            ]
        }
        
        validator = ContestValidator(questionnaire)
        
        assert validator.rules['PRESIDENT'] == 1
        assert validator.rules['SENATOR'] == 12
        assert validator.rules['REPRESENTATIVE-PARTY-LIST'] == 1
    
    def test_valid_votes_pass(self):
        """Test valid votes pass validation."""
        questionnaire = {
            'positions': [
                {'code': 'PRESIDENT', 'max_selections': 1},
                {'code': 'SENATOR', 'max_selections': 3}
            ]
        }
        
        validator = ContestValidator(questionnaire)
        
        votes = {
            'PRESIDENT_LD_001': True,
            'SENATOR_JD_001': True,
            'SENATOR_ES_002': True
        }
        
        results = validator.validate(votes)
        
        assert results['PRESIDENT']['valid'] == True
        assert results['PRESIDENT']['count'] == 1
        assert results['SENATOR']['valid'] == True
        assert results['SENATOR']['count'] == 2
    
    def test_overvote_detected(self):
        """Test overvote is detected."""
        questionnaire = {
            'positions': [
                {'code': 'PRESIDENT', 'max_selections': 1}
            ]
        }
        
        validator = ContestValidator(questionnaire)
        
        votes = {
            'PRESIDENT_LD_001': True,
            'PRESIDENT_SJ_002': True  # Overvote!
        }
        
        results = validator.validate(votes)
        
        assert results['PRESIDENT']['overvote'] == True
        assert results['PRESIDENT']['valid'] == False
        assert results['PRESIDENT']['count'] == 2
        assert results['PRESIDENT']['max'] == 1
    
    def test_get_overvotes(self):
        """Test getting list of positions with overvotes."""
        questionnaire = {
            'positions': [
                {'code': 'PRESIDENT', 'max_selections': 1},
                {'code': 'SENATOR', 'max_selections': 2}
            ]
        }
        
        validator = ContestValidator(questionnaire)
        
        votes = {
            'PRESIDENT_LD_001': True,
            'PRESIDENT_SJ_002': True,  # Overvote
            'SENATOR_JD_001': True,
            'SENATOR_ES_002': True,
            'SENATOR_MF_003': True  # Overvote
        }
        
        overvotes = validator.get_overvotes(votes)
        
        assert 'PRESIDENT' in overvotes
        assert 'SENATOR' in overvotes
        assert len(overvotes) == 2


class TestAudioFeedback:
    """Test audio feedback (basic initialization only)."""
    
    def test_audio_disabled_mode(self):
        """Test audio can be disabled."""
        audio = AudioFeedback(enabled=False)
        
        assert audio.enabled == False
        
        # Should not raise errors when called
        audio.ballot_detected()
        audio.vote_registered()
        audio.overvote_warning()


def run_tests():
    """Run all tests without pytest."""
    test_classes = [
        TestVoteAccumulator,
        TestBallotSession,
        TestContestValidator,
        TestAudioFeedback
    ]
    
    total_tests = 0
    passed_tests = 0
    failed_tests = 0
    
    for test_class in test_classes:
        class_name = test_class.__name__
        print(f'\n{class_name}:')
        print('=' * 60)
        
        instance = test_class()
        test_methods = [m for m in dir(instance) if m.startswith('test_')]
        
        for method_name in test_methods:
            total_tests += 1
            method = getattr(instance, method_name)
            
            try:
                method()
                passed_tests += 1
                print(f'  ✓ {method_name}')
            except AssertionError as e:
                failed_tests += 1
                print(f'  ✗ {method_name}')
                print(f'    AssertionError: {e}')
            except Exception as e:
                failed_tests += 1
                print(f'  ✗ {method_name}')
                print(f'    Exception: {e}')
                traceback.print_exc()
    
    print('\n' + '=' * 60)
    print(f'SUMMARY: {passed_tests}/{total_tests} tests passed')
    print('=' * 60)
    
    return 0 if failed_tests == 0 else 1


if __name__ == '__main__':
    sys.exit(run_tests())
