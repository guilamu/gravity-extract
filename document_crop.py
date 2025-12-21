#!/usr/bin/env python3
"""
Document Auto-Crop Script for Gravity Extract Plugin
Uses OpenCV to detect and crop document boundaries from images.
"""

import sys
import os
import shutil

try:
    import cv2
    import numpy as np
except ImportError:
    print("ERROR: OpenCV (cv2) not installed", file=sys.stderr)
    sys.exit(1)


def order_points(pts):
    """Order points in top-left, top-right, bottom-right, bottom-left order."""
    rect = np.zeros((4, 2), dtype="float32")
    s = pts.sum(axis=1)
    rect[0] = pts[np.argmin(s)]
    rect[2] = pts[np.argmax(s)]
    diff = np.diff(pts, axis=1)
    rect[1] = pts[np.argmin(diff)]
    rect[3] = pts[np.argmax(diff)]
    return rect


def find_document_contour(image):
    """Find the largest rectangular contour in the image."""
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    gray = cv2.GaussianBlur(gray, (5, 5), 0)
    edged = cv2.Canny(gray, 75, 200)
    
    # Dilate to close gaps in edges
    kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (5, 5))
    edged = cv2.dilate(edged, kernel, iterations=1)
    
    contours, _ = cv2.findContours(edged, cv2.RETR_LIST, cv2.CHAIN_APPROX_SIMPLE)
    contours = sorted(contours, key=cv2.contourArea, reverse=True)[:5]
    
    document_contour = None
    
    for contour in contours:
        peri = cv2.arcLength(contour, True)
        approx = cv2.approxPolyDP(contour, 0.02 * peri, True)
        
        # Look for quadrilateral
        if len(approx) == 4:
            document_contour = approx
            break
    
    return document_contour


def crop_document(image, contour, margin_percent=0.05):
    """Crop image to the bounding rectangle of the contour with margin."""
    x, y, w, h = cv2.boundingRect(contour)
    
    # Add margin
    margin_x = int(w * margin_percent)
    margin_y = int(h * margin_percent)
    
    x = max(0, x - margin_x)
    y = max(0, y - margin_y)
    w = min(image.shape[1] - x, w + 2 * margin_x)
    h = min(image.shape[0] - y, h + 2 * margin_y)
    
    return image[y:y+h, x:x+w]


def process_image(input_path, output_path):
    """Main processing function."""
    if not os.path.exists(input_path):
        print(f"ERROR: Input file not found: {input_path}", file=sys.stderr)
        return False
    
    image = cv2.imread(input_path)
    if image is None:
        print(f"ERROR: Could not read image: {input_path}", file=sys.stderr)
        return False
    
    contour = find_document_contour(image)
    
    if contour is not None:
        cropped = crop_document(image, contour)
        cv2.imwrite(output_path, cropped)
    else:
        # No contour found, copy original
        shutil.copy2(input_path, output_path)
    
    return True


def main():
    if len(sys.argv) != 3:
        print("Usage: document_crop.py <input_path> <output_path>", file=sys.stderr)
        sys.exit(1)
    
    input_path = sys.argv[1]
    output_path = sys.argv[2]
    
    if process_image(input_path, output_path):
        # Print output path for PHP to capture
        print(output_path)
        sys.exit(0)
    else:
        sys.exit(1)


if __name__ == "__main__":
    main()
