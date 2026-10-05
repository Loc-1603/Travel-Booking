import { describe, it, expect, vi } from "vitest";
import { render, screen, fireEvent } from "@testing-library/react";
import { TourGallery } from "./TourGallery";

vi.mock("./LazyImage", () => ({
  default: ({ src, alt, className }) => (
    <img src={src} alt={alt} className={className} data-testid="lazy-img" />
  ),
}));

describe("TourGallery", () => {
  const images = ["https://example.com/1.jpg", "https://example.com/2.jpg"];

  it("renders thumbnail for each image", () => {
    render(<TourGallery images={images} />);
    const thumbnails = screen.getAllByRole("button", { name: /view photo/i });
    expect(thumbnails).toHaveLength(2);
  });

  it("shows loading placeholder when no images", () => {
    render(<TourGallery images={[]} />);
    expect(screen.getByText("No photos available")).toBeInTheDocument();
  });

  it("opens lightbox on main image click", () => {
    render(<TourGallery images={images} />);
    const openButton = screen.getByRole("button", { name: "Open full-screen gallery" });
    fireEvent.click(openButton);
    expect(screen.getByRole("dialog")).toBeInTheDocument();
  });

  it("closes lightbox on close button", () => {
    render(<TourGallery images={images} />);
    const openButton = screen.getByRole("button", { name: "Open full-screen gallery" });
    fireEvent.click(openButton);
    expect(screen.getByRole("dialog")).toBeInTheDocument();
    const closeButton = screen.getByRole("button", { name: "Close gallery" });
    fireEvent.click(closeButton);
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });

  it("navigates with prev/next buttons", () => {
    render(<TourGallery images={images} />);
    fireEvent.click(screen.getByRole("button", { name: "View photo 2" }));
    expect(screen.getByRole("dialog")).toHaveAttribute("aria-label", "Photo 2 of 2");
    const nextButtons = screen.getAllByRole("button", { name: "Next photo" });
    expect(nextButtons).not.toHaveLength(0);
    nextButtons.forEach((b) => expect(b).toBeDisabled());
    const prevButtons = screen.getAllByRole("button", { name: "Previous photo" });
    fireEvent.click(prevButtons[0]);
    expect(screen.getByRole("dialog")).toHaveAttribute("aria-label", "Photo 1 of 2");
  });

  it("handles ESC key", () => {
    render(<TourGallery images={images} />);
    const openButton = screen.getByRole("button", { name: "Open full-screen gallery" });
    fireEvent.click(openButton);
    expect(screen.getByRole("dialog")).toBeInTheDocument();
    fireEvent.keyDown(document, { key: "Escape" });
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });
});
