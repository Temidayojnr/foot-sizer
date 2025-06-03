import sys
import json
import cv2
import numpy as np
# import filetype
import os

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No image path provided"}))
        return

    image_path = sys.argv[1]
    # if filetype.what(image_path) not in ['jpeg','jpg','png']:
    #     print(json.dumps({"error": "Invalid image format"}))
    #     return
    ext = os.path.splitext(image_path)[1].lower()
    if ext not in ['.jpg', '.jpeg', '.png']:
        print(json.dumps({"error": "Invalid image format"}))
        return

    # Load image
    img = cv2.imread(image_path)
    if img is None:
        print(json.dumps({"error": "Unable to load image"}))
        return

    # Convert to grayscale and blur to reduce noise
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    blurred = cv2.GaussianBlur(gray, (7, 7), 0)

    # Edge detection
    edged = cv2.Canny(blurred, 50, 150)

    # Find contours
    contours, _ = cv2.findContours(edged.copy(), cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

    if len(contours) < 2:
        print(json.dumps({"error": "Not enough contours detected"}))
        return

    # Sort by area (descending)
    contours = sorted(contours, key=cv2.contourArea, reverse=True)[:5]

    # Get top 2 largest contours
    box1 = cv2.boundingRect(contours[0])
    box2 = cv2.boundingRect(contours[1])

    # Decide which is A4 and which is foot (A4 likely wider, more rectangular)
    if box1[2] > box2[2]:
        a4_box = box1
        foot_box = box2
    else:
        a4_box = box2
        foot_box = box1

    # Get width in pixels of A4
    a4_width_px = a4_box[2]
    known_a4_width_cm = 21.0

    pixels_per_cm = a4_width_px / known_a4_width_cm

    # Use foot height in pixels as its length (assuming vertical photo)
    foot_length_px = foot_box[3]
    foot_length_cm = round(foot_length_px / pixels_per_cm, 1)

    # Result
    result = {
        "foot_size_cm": foot_length_cm
    }

    print(json.dumps(result))


if __name__ == "__main__":
    main()
