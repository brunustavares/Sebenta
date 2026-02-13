<p align="center">
  <img src="pix/logo.png" alt="Sebenta Logo" width="300">
</p>

# Sebenta
Moodle block developed for Universidade Aberta (UAb) to bridge the gap between Moodle and the WISEflow assessment platform. It provides distinct, role-based functionalities for both teaching staff and students, streamlining workflows and centralizing academic information.

*   **For Teachers, Reviewers, and Managers:** It offers a dashboard to monitor the progress of grading in WISEflow and provides a one-click function to finalize the assessment period, which synchronizes grades and makes them available to students.
*   **For Students:** It presents an integrated and interactive "learning card" carousel, displaying grades and direct links to electronic submission certificates for their course units, consolidating information from both Moodle and WISEflow.

This block was originally developed for [Universidade Aberta (UAb)](https://portal.uab.pt/).

## Features

### Teacher & Manager View

This view is available to users with specific Moodle roles (`wfrev` for Reviewer, `wfman` for Manager).

*   **Flow Monitoring Dashboard:** Displays a list of active WISEflow "flows" (assessments) that are currently in the marking period.
*   **Grading Progress Bars:** Each flow is accompanied by a visual progress bar indicating the percentage of submitted papers that have been graded. The bar is color-coded:
    *   **Orange:** Grading is in progress.
    *   **Green:** All submissions have been graded (100% complete).
    *   **Red:** Inconsistent data (e.g., more grades than submissions) or no grades submitted.
*   **Detailed Tooltips:** Hovering over a progress bar reveals a tooltip with exact numbers (e.g., "15 grades submitted, out of 20 submissions").
*   **Direct Link to WISEflow:** Each flow title links directly to the corresponding manager page in WISEflow for easy access.
*   **Finalize Assessment:** A "Finalize" button is available for flows with 100% grading completion. Clicking this button:
    1.  Opens a confirmation modal explaining the consequences (ending the marking period, locking grades, and releasing them to students).
    2.  On confirmation, it triggers an asynchronous API call to WISEflow to set the marking end date to the current time.
    3.  The page then reloads to reflect the updated status.

### Student View

This view is available to users with the 'student' role (or equivalent).

*   **Learning Card Carousel:** Displays a horizontally scrollable carousel of "cards," where each card represents a course unit the student is enrolled in.
*   **Integrated Grade Display:** Each card contains a table listing the student's assessment items (e.g., e-folios, exams) and their corresponding final grades.
*   **Submission Certificates:** For each assessment item, it provides a direct link to the electronic submission statement/certificate. These certificates are fetched from another Moodle block (`lanca_pauta`) and consolidated here.
*   **Data Aggregation:** The block intelligently gathers data from multiple sources:
    *   Moodle's own gradebook for local assignments.
    *   The `lanca_pauta` block for certificate information.
    *   WISEflow (via the intermediate database) for exam grades.
*   **Intuitive Navigation:** Simple "previous" and "next" buttons allow for easy navigation through the course cards. The carousel is designed with an "infinite loop" for a seamless user experience.

## Architecture and Technical Details

The `Sebenta` block is composed of several key files that work together to deliver its functionality.

### Backend (PHP)

*   **`block_sebenta.php`**: The main Moodle block class.
    *   It handles all server-side logic, including role detection, permission checks, and data fetching.
    *   It dynamically builds the initial HTML structure and serves as the endpoint for asynchronous data requests.
    *   **Optimization**: Supports paginated data retrieval (`get_flows` action) to efficiently handle large numbers of flows without overloading the initial page load.
    *   For the student view, it queries the Moodle database and interacts with the `block_lanca_pauta` to aggregate course, grade, and certificate data.

*   **`wf_endpoints.php`**: A dedicated endpoint for handling AJAX requests from the block's frontend.
    *   It currently implements the `endflowmarking` action.
    *   It receives the `flowid`, authentication token, and API URL from the client-side JavaScript.
    *   It securely performs a `PATCH` request to the WISEflow API to update the flow's marking end date.
    *   **Optimization**: Uses centralized cURL parameter configuration for consistent and secure API communication.

*   **`fetch_flows.php`**: Contains helper functions for fetching data from external sources.
    *   `getbdintdata()`: Connects to an intermediate database/webservice to retrieve the list of WISEflow flows relevant to the user.
    *   `checkwftoken()`: Manages the WISEflow API authentication token, including fetching a new one if the current one is expired or missing.
    *   **Optimization**: Designed to work with the paginated requests from the frontend, fetching only the necessary subset of flow data per request.

*   **`settings.php`**: Defines the configuration settings for the block, likely including API base URLs, credentials, and other administrative options accessible through Moodle's block settings page.

### Frontend (JavaScript & CSS)

*   **`script.js`**: Contains all client-side JavaScript logic.
    *   **Teacher/Manager Functions (`initTeacherFlows`):**
        *   **Asynchronous Loading**: Uses the Fetch API to load flow data in batches (pagination), significantly improving initial page load time.
        *   **Client-Side Caching**: Implements `sessionStorage` caching (2-minute TTL) to store loaded flows and status, reducing server load and API calls on page refreshes.
        *   **Dynamic UI**: Handles "Load More" functionality, loading spinners, and real-time status updates.
        *   `endflowmarking()`: Handles the AJAX `POST` request to `wf_endpoints.php` to finalize an assessment, passing the necessary data. It also updates the UI to show a confirmation message and reloads the page.
    *   **Student Functions (`initStudentCarousel`):**
        *   **Responsive Carousel**: Manages the student's learning card carousel with dynamic slide sizing based on container width.
        *   **Enhanced Navigation**: Supports touch/pointer swipe gestures and keyboard navigation (Arrow keys).
        *   **State Persistence**: Caches the carousel content and current slide index in `sessionStorage` for a seamless user experience across page reloads.

*   **`style.css`**: Contains all the CSS rules for styling the block's components, including:
    *   The layout and appearance of the progress bars for the teacher view.
    *   The design of the learning cards, tables, and navigation buttons for the student carousel.
    *   The styling for the confirmation modal.

## Installation and Configuration

1.  Place the `sebenta` folder inside the `blocks/` directory of your Moodle installation.
2.  Navigate to `Site administration > Notifications` in Moodle to trigger the installation process.
3.  Configure the required Moodle roles:
    *   `wfrev` (WISEflow Reviewer)
    *   `wfman` (WISEflow Manager)
4.  Assign the `block/sebenta:view` capability to the appropriate roles (e.g., student, teacher, manager) to control who can see the block.
5.  Configure the block's global settings in `Site administration > Plugins > Blocks > Sebenta`. This will include setting up API endpoints, authentication tokens, and database connection details for the intermediate database.
6.  Add the "Sebenta" block to the desired pages in Moodle (e.g., the user dashboard).

## Dependencies

*   **Moodle:** A running Moodle instance.
*   **WISEflow:** An active WISEflow account with API access enabled.
*   **`block_lanca_pauta`:** Another Moodle block that must be installed and configured, as Sebenta relies on it to fetch student submission certificates.
*   **Intermediate Database/Webservice:** An external data source is required to provide the initial list of WISEflow flows to the block. The connection details are configured in the block settings.

## Licenses

**Author**: Bruno Tavares  
**Contact**: [brunustavares@gmail.com](mailto:brunustavares@gmail.com)  
**LinkedIn**: [https://www.linkedin.com/in/brunomastavares/](https://www.linkedin.com/in/brunomastavares/)  
**Copyright**: 2023-present Bruno Tavares  
**License**: GNU GPL v3 or later  

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.

### Assets

- **Source code**: GNU GPL v3 or later (© Bruno Tavares)  
- **Images**: © Universidade Aberta, provided by the Digital Production Services, all rights reserved. Usage subject to the institution's policy.
