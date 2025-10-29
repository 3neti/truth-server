#!/usr/bin/env python3
"""
Compare appreciation results against ground truth.

Usage:
    python3 compare_appreciation_results.py \\
        --result results.json \\
        --truth ground-truth.json \\
        --output report.json
"""
import json
import sys
import argparse
from typing import Dict


def compare_results(result: Dict, truth: Dict) -> Dict:
    """Compare appreciation results against ground truth.
    
    Args:
        result: Appreciation output (with 'results' array)
        truth: Ground truth with 'bubble_states' dict
        
    Returns:
        Comparison metrics dict
    """
    expected = truth['bubble_states']
    confidence_threshold = truth.get('confidence_threshold', 0.95)
    
    # Extract detected bubbles from appreciation results
    results = result.get('results', [])
    detected = set()
    
    for bubble in results:
        if bubble.get('filled') and bubble.get('fill_ratio', 0) >= confidence_threshold:
            detected.add(bubble['id'])
    
    # Calculate confusion matrix
    tp = fp = fn = tn = 0
    errors = []
    
    for bubble_id, expected_state in expected.items():
        detected_state = bubble_id in detected
        
        if expected_state and detected_state:
            tp += 1
        elif expected_state and not detected_state:
            fn += 1
            errors.append({
                'bubble': bubble_id,
                'error': 'false_negative',
                'expected': True,
                'detected': False
            })
        elif not expected_state and detected_state:
            fp += 1
            errors.append({
                'bubble': bubble_id,
                'error': 'false_positive',
                'expected': False,
                'detected': True
            })
        else:
            tn += 1
    
    # Calculate metrics
    total = tp + fp + fn + tn
    accuracy = tp / (tp + fp + fn) if (tp + fp + fn) > 0 else 0
    precision = tp / (tp + fp) if (tp + fp) > 0 else 0
    recall = tp / (tp + fn) if (tp + fn) > 0 else 0
    f1_score = 2 * (precision * recall) / (precision + recall) if (precision + recall) > 0 else 0
    
    # False positive/negative rates
    fp_rate = fp / (fp + tn) if (fp + tn) > 0 else 0
    fn_rate = fn / (fn + tp) if (fn + tp) > 0 else 0
    
    # Check against success criteria
    criteria = truth.get('success_criteria', {})
    min_accuracy = criteria.get('mark_accuracy', 0.98)
    max_fp_rate = criteria.get('false_positive_rate', 0.01)
    max_fn_rate = criteria.get('false_negative_rate', 0.02)
    
    passes_accuracy = accuracy >= min_accuracy
    passes_fp = fp_rate <= max_fp_rate
    passes_fn = fn_rate <= max_fn_rate
    
    verdict = 'PASS' if (passes_accuracy and passes_fp and passes_fn) else 'FAIL'
    
    return {
        'verdict': verdict,
        'accuracy': accuracy,
        'precision': precision,
        'recall': recall,
        'f1_score': f1_score,
        'confusion_matrix': {
            'true_positives': tp,
            'false_positives': fp,
            'false_negatives': fn,
            'true_negatives': tn,
            'total': total
        },
        'rates': {
            'false_positive_rate': fp_rate,
            'false_negative_rate': fn_rate
        },
        'criteria_checks': {
            'accuracy': {'pass': passes_accuracy, 'threshold': min_accuracy, 'actual': accuracy},
            'fp_rate': {'pass': passes_fp, 'threshold': max_fp_rate, 'actual': fp_rate},
            'fn_rate': {'pass': passes_fn, 'threshold': max_fn_rate, 'actual': fn_rate}
        },
        'errors': errors,
        'summary': {
            'expected_marks': truth['total_expected_marks'],
            'detected_marks': len(detected),
            'matched_marks': tp
        }
    }


def main():
    parser = argparse.ArgumentParser(
        description='Compare appreciation results against ground truth'
    )
    parser.add_argument('--result', required=True, help='Appreciation results JSON')
    parser.add_argument('--truth', required=True, help='Ground truth JSON')
    parser.add_argument('--output', required=True, help='Output report JSON')
    parser.add_argument('--verbose', action='store_true', help='Print detailed report')
    args = parser.parse_args()
    
    # Load files
    try:
        with open(args.result) as f:
            result = json.load(f)
    except Exception as e:
        print(f"ERROR: Failed to load result file: {e}", file=sys.stderr)
        sys.exit(1)
    
    try:
        with open(args.truth) as f:
            truth = json.load(f)
    except Exception as e:
        print(f"ERROR: Failed to load truth file: {e}", file=sys.stderr)
        sys.exit(1)
    
    # Compare
    comparison = compare_results(result, truth)
    
    # Save output
    try:
        with open(args.output, 'w') as f:
            json.dump(comparison, f, indent=2)
    except Exception as e:
        print(f"ERROR: Failed to write output: {e}", file=sys.stderr)
        sys.exit(1)
    
    # Print summary
    print(f"Verdict: {comparison['verdict']}")
    print(f"Accuracy: {comparison['accuracy']*100:.2f}%")
    print(f"Precision: {comparison['precision']*100:.2f}%")
    print(f"Recall: {comparison['recall']*100:.2f}%")
    print(f"F1 Score: {comparison['f1_score']:.4f}")
    print(f"FP Rate: {comparison['rates']['false_positive_rate']*100:.2f}%")
    print(f"FN Rate: {comparison['rates']['false_negative_rate']*100:.2f}%")
    
    if args.verbose and comparison['errors']:
        print(f"\nErrors ({len(comparison['errors'])}):")
        for err in comparison['errors']:
            print(f"  - {err['bubble']}: {err['error']}")
    
    # Exit code based on verdict
    sys.exit(0 if comparison['verdict'] == 'PASS' else 1)


if __name__ == '__main__':
    main()
