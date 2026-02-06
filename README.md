# Open Street Map WordPress Plugin

A powerful and modern WordPress plugin to display an interactive OpenStreetMap on your website. Manage cities and signs with a beautiful, user-friendly admin interface.

## Features

-   **Interactive Map**: Display a responsive OpenStreetMap using Leaflet/MapLibre.
-   **City & Sign Management**: Easily organize your locations. Group Signs under Cities for clustered views.
-   **Modern Admin UI**: Enjoy a sleek, tabbed interface for managing Cities and Signs.
    -   **Cities**: Set coordinates, display counts, and choose venue pins.
    -   **Signs**: link to cities, set coordinates, add media images, and configure call-to-action buttons.
-   **Dynamic CTA Logic**:
    -   Set a global "Default CTA URL" for all map popups.
    -   Override or disable the CTA button on a per-sign basis.
-   **Customization**:
    -   **Venue Pins**: Choose from a library of visual pins for your locations.
    -   **Colors**: Customize popup text color and map bubble background colors via the Settings page.
-   **Search Functionality**: Integrated city search with auto-suggestions.

## Installation

1.  Download the plugin folder `Open-Street-Map-WordPress-Plugin`.
2.  Upload the folder to your `wp-content/plugins/` directory.
3.  Activate the plugin through the 'Plugins' menu in WordPress.

## Usage

### 1. Add Cities
Go to **OpenStreetMap > Cities** and click "Add New City".
-   **City Details**: Enter Latitude, Longitude, and the Display Count (number shown on the cluster).
-   **Venue Pin**: Select a pin icon from the visual grid.

### 2. Add Signs
Go to **OpenStreetMap > Signs** and click "Add New Sign".
-   **Map Details**: Select the parent City and set the Sign's Latitude/Longitude.
-   **Venue Pin**: Choose a specific pin for this sign (optional, defaults available).
-   **Media**: Add an external image URL for the popup header.
-   **Action Button**: Configure the "Log in to get started" button. You can use the global default, set a custom URL, or disable it entirely for this specific sign.

### 3. Settings
Go to **OpenStreetMap > Settings**.
-   **General**: Set the global "Default CTA URL" and toggle the button visibility.
-   **Colors**: Customize the look of your map popups.

### 4. Display the Map
Add the following shortcode to any page or post to display the map:

```
[open_street_map]
```

## Shortcuts
-   **City Actions**: From the City edit screen, you can quickly "Add New Sign" which automatically pre-selects the current city.
