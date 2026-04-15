#!/usr/bin/env python3
"""
Analyse d'images avec NudeNet pour détection de contenu explicite
Usage: python analyze_image.py <image_path>
Output: JSON {"safe": float, "unsafe": float}
"""

import sys
import json

def analyze_image(image_path):
    import json
    
    try:
        import sys
        import os
        import site
        site.main()
        
        from nudenet import NudeDetector
        
        detector = NudeDetector()
        detections = detector.detect(image_path)
        
        explicit_categories = [
            'FEMALE_GENITALIA_COVERED', 'FEMALE_GENITALIA_EXPOSED',
            'MALE_GENITALIA_COVERED', 'MALE_GENITALIA_EXPOSED',
            'FEMALE_BREAST_COVERED', 'FEMALE_BREAST_EXPOSED',
            'MALE_BREAST_EXPOSED', 'ANUS_COVERED', 'ANUS_EXPOSED',
            'BUTTOCKS_COVERED', 'BUTTOCKS_EXPOSED'
        ]
        
        unsafe_score = 0.0
        for detection in detections:
            score = detection.get('score', 0)
            label = detection.get('class', '')
            if label in explicit_categories:
                unsafe_score = max(unsafe_score, score)
        
        result = {
            "safe": 1.0 - unsafe_score,
            "unsafe": unsafe_score
        }
        
        print(json.dumps(result))
        return 0
        
    except ImportError as e:
        import sys
        print(json.dumps({
            "error": f"nudenet not installed or import failed: {str(e)}, sys.path={sys.path[:3]}",
            "safe": 1.0,
            "unsafe": 0.0
        }))
        return 1
        
    except Exception as e:
        print(json.dumps({"error": str(e), "safe": 1.0, "unsafe": 0.0}))
        return 1

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print(json.dumps({"error": "Usage: analyze_image.py <image_path>"}))
        sys.exit(1)
    
    image_path = sys.argv[1]
    sys.exit(analyze_image(image_path))
