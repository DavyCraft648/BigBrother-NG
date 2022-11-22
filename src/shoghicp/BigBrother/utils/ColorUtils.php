<?php
/**
 *  ______  __         ______               __    __
 * |   __ \|__|.-----.|   __ \.----..-----.|  |_ |  |--..-----..----.
 * |   __ <|  ||  _  ||   __ <|   _||  _  ||   _||     ||  -__||   _|
 * |______/|__||___  ||______/|__|  |_____||____||__|__||_____||__|
 *             |_____|
 *
 * BigBrother plugin for PocketMine-MP
 * Copyright (C) 2014-2015 shoghicp <https://github.com/shoghicp/BigBrother>
 * Copyright (C) 2016- BigBrotherTeam
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author BigBrotherTeam
 * @link   https://github.com/BigBrotherTeam/BigBrother
 *
 */

declare(strict_types=1);

namespace shoghicp\BigBrother\utils;

use pocketmine\color\Color;
use function chr;
use function file_get_contents;
use function file_put_contents;
use function zlib_decode;
use function zlib_encode;

class ColorUtils{

	// TODO this color table is not up-to-date (please update me!!)
	private static array $colorTable = [
		0x04 => [0x59, 0x7D, 0x27], // Grass
		0x05 => [0x6D, 0x99, 0x30], // Grass
		0x06 => [0x7F, 0xB2, 0x38], // Grass
		0x07 => [0x6D, 0x99, 0x30], // Grass
		0x08 => [0xAE, 0xA4, 0x73], // Sand
		0x09 => [0xD5, 0xC9, 0x8C], // Sand
		0x0A => [0xF7, 0xE9, 0xA3], // Sand
		0x0B => [0xD5, 0xC9, 0x8C], // Sand
		0x0C => [0x75, 0x75, 0x75], // Cloth
		0x0D => [0x90, 0x90, 0x90], // Cloth
		0x0E => [0xA7, 0xA7, 0xA7], // Cloth
		0x0F => [0x90, 0x90, 0x90], // Cloth
		0x10 => [0xB4, 0x00, 0x00], // Fire
		0x11 => [0xDC, 0x00, 0x00], // Fire
		0x12 => [0xFF, 0x00, 0x00], // Fire
		0x13 => [0xDC, 0x00, 0x00], // Fire
		0x14 => [0x70, 0x70, 0xB4], // Ice
		0x15 => [0x8A, 0x8A, 0xDC], // Ice
		0x16 => [0xA0, 0xA0, 0xFF], // Ice
		0x17 => [0x8A, 0x8A, 0xDC], // Ice
		0x18 => [0x75, 0x75, 0x75], // Iron
		0x19 => [0x90, 0x90, 0x90], // Iron
		0x1A => [0xA7, 0xA7, 0xA7], // Iron
		0x1B => [0x90, 0x90, 0x90], // Iron
		0x1C => [0x00, 0x57, 0x00], // Foliage
		0x1D => [0x00, 0x6A, 0x00], // Foliage
		0x1E => [0x00, 0x7C, 0x00], // Foliage
		0x1F => [0x00, 0x6A, 0x00], // Foliage
		0x20 => [0xB4, 0xB4, 0xB4], // Snow
		0x21 => [0xDC, 0xDC, 0xDC], // Snow
		0x22 => [0xFF, 0xFF, 0xFF], // Snow
		0x23 => [0xDC, 0xDC, 0xDC], // Snow
		0x24 => [0x73, 0x76, 0x81], // Clay
		0x25 => [0x8D, 0x90, 0x9E], // Clay
		0x26 => [0xA4, 0xA8, 0xB8], // Clay
		0x27 => [0x8D, 0x90, 0x9E], // Clay
		0x28 => [0x81, 0x4A, 0x21], // Dirt
		0x29 => [0x9D, 0x5B, 0x28], // Dirt
		0x2A => [0xB7, 0x6A, 0x2F], // Dirt
		0x2B => [0x9D, 0x5B, 0x28], // Dirt
		0x2C => [0x4F, 0x4F, 0x4F], // Stone
		0x2D => [0x60, 0x60, 0x60], // Stone
		0x2E => [0x70, 0x70, 0x70], // Stone
		0x2F => [0x60, 0x60, 0x60], // Stone
		0x30 => [0x2D, 0x2D, 0xB4], // Water
		0x31 => [0x37, 0x37, 0xDC], // Water
		0x32 => [0x40, 0x40, 0xFF], // Water
		0x33 => [0x37, 0x37, 0xDC], // Water
		0x34 => [0x49, 0x3A, 0x23], // Wood
		0x35 => [0x59, 0x47, 0x2B], // Wood
		0x36 => [0x68, 0x53, 0x32], // Wood
		0x37 => [0x59, 0x47, 0x2B], // Wood
		0x38 => [0xB4, 0xB1, 0xAC], // Quartz, Sea Lantern, Birch Log
		0x39 => [0xDC, 0xD9, 0xD3], // Quartz, Sea Lantern, Birch Log
		0x3A => [0xFF, 0xFC, 0xF5], // Quartz, Sea Lantern, Birch Log
		0x3B => [0x87, 0x85, 0x81], // Quartz, Sea Lantern, Birch Log
		0x3C => [0x98, 0x59, 0x24], // Orange Wool/Glass/Stained Clay, Pumpkin, Hardened Clay, Acacia Plank
		0x3D => [0xBA, 0x6D, 0x2C], // Orange Wool/Glass/Stained Clay, Pumpkin, Hardened Clay, Acacia Plank
		0x3E => [0xD8, 0x7F, 0x33], // Orange Wool/Glass/Stained Clay, Pumpkin, Hardened Clay, Acacia Plank
		0x3F => [0x72, 0x43, 0x1B], // Orange Wool/Glass/Stained Clay, Pumpkin, Hardened Clay, Acacia Plank
		0x40 => [0x7D, 0x35, 0x98], // Magenta Wool/Glass/Stained Clay
		0x41 => [0x99, 0x41, 0xBA], // Magenta Wool/Glass/Stained Clay
		0x42 => [0xB2, 0x4C, 0xD8], // Magenta Wool/Glass/Stained Clay
		0x43 => [0x5E, 0x28, 0x72], // Magenta Wool/Glass/Stained Clay
		0x44 => [0x48, 0x6C, 0x98], // Light Blue Wool/Glass/Stained Clay
		0x45 => [0x58, 0x84, 0xBA], // Light Blue Wool/Glass/Stained Clay
		0x46 => [0x66, 0x99, 0xD8], // Light Blue Wool/Glass/Stained Clay
		0x47 => [0x36, 0x51, 0x72], // Light Blue Wool/Glass/Stained Clay
		0x48 => [0xA1, 0xA1, 0x24], // Yellow Wool/Glass/Stained Clay, Sponge, Hay Bale
		0x49 => [0xC5, 0xC5, 0x2C], // Yellow Wool/Glass/Stained Clay, Sponge, Hay Bale
		0x4A => [0xE5, 0xE5, 0x33], // Yellow Wool/Glass/Stained Clay, Sponge, Hay Bale
		0x4B => [0x79, 0x79, 0x1B], // Yellow Wool/Glass/Stained Clay, Sponge, Hay Bale
		0x4C => [0x59, 0x90, 0x11], // Lime Wool/Glass/Stained Clay, Melon
		0x4D => [0x6D, 0xB0, 0x15], // Lime Wool/Glass/Stained Clay, Melon
		0x4E => [0x7F, 0xCC, 0x19], // Lime Wool/Glass/Stained Clay, Melon
		0x4F => [0x43, 0x6C, 0x0D], // Lime Wool/Glass/Stained Clay, Melon
		0x50 => [0xAA, 0x59, 0x74], // Pink Wool/Glass/Stained Clay
		0x51 => [0xD0, 0x6D, 0x8E], // Pink Wool/Glass/Stained Clay
		0x52 => [0xF2, 0x7F, 0xA5], // Pink Wool/Glass/Stained Clay
		0x53 => [0x80, 0x43, 0x57], // Pink Wool/Glass/Stained Clay
		0x54 => [0x35, 0x35, 0x35], // Grey Wool/Glass/Stained Clay
		0x55 => [0x41, 0x41, 0x41], // Grey Wool/Glass/Stained Clay
		0x56 => [0x4C, 0x4C, 0x4C], // Grey Wool/Glass/Stained Clay
		0x57 => [0x28, 0x28, 0x28], // Grey Wool/Glass/Stained Clay
		0x58 => [0x6C, 0x6C, 0x6C], // Light Grey Wool/Glass/Stained Clay
		0x59 => [0x84, 0x84, 0x84], // Light Grey Wool/Glass/Stained Clay
		0x5A => [0x99, 0x99, 0x99], // Light Grey Wool/Glass/Stained Clay
		0x5B => [0x51, 0x51, 0x51], // Light Grey Wool/Glass/Stained Clay
		0x5C => [0x35, 0x59, 0x6C], // Cyan Wool/Glass/Stained Clay
		0x5D => [0x41, 0x6D, 0x84], // Cyan Wool/Glass/Stained Clay
		0x5E => [0x4C, 0x7F, 0x99], // Cyan Wool/Glass/Stained Clay
		0x5F => [0x28, 0x43, 0x51], // Cyan Wool/Glass/Stained Clay
		0x60 => [0x59, 0x2C, 0x7D], // Purple Wool/Glass/Stained Clay, Mycelium
		0x61 => [0x6D, 0x36, 0x99], // Purple Wool/Glass/Stained Clay, Mycelium
		0x62 => [0x7F, 0x3F, 0xB2], // Purple Wool/Glass/Stained Clay, Mycelium
		0x63 => [0x43, 0x21, 0x5E], // Purple Wool/Glass/Stained Clay, Mycelium
		0x64 => [0x24, 0x35, 0x7D], // Blue Wool/Glass/Stained Clay
		0x65 => [0x2C, 0x41, 0x99], // Blue Wool/Glass/Stained Clay
		0x66 => [0x33, 0x4C, 0xB2], // Blue Wool/Glass/Stained Clay
		0x67 => [0x1B, 0x28, 0x5E], // Blue Wool/Glass/Stained Clay
		0x68 => [0x48, 0x35, 0x24], // Brown Wool/Glass/Stained Clay, Soul Sand, Dark Oak Plank
		0x69 => [0x58, 0x41, 0x2C], // Brown Wool/Glass/Stained Clay, Soul Sand, Dark Oak Plank
		0x6A => [0x66, 0x4C, 0x33], // Brown Wool/Glass/Stained Clay, Soul Sand, Dark Oak Plank
		0x6B => [0x36, 0x28, 0x1B], // Brown Wool/Glass/Stained Clay, Soul Sand, Dark Oak Plank
		0x6C => [0x48, 0x59, 0x24], // Green Wool/Glass/Stained Clay, End Portal Frame
		0x6D => [0x58, 0x6D, 0x2C], // Green Wool/Glass/Stained Clay, End Portal Frame
		0x6E => [0x66, 0x7F, 0x33], // Green Wool/Glass/Stained Clay, End Portal Frame
		0x6F => [0x36, 0x43, 0x1B], // Green Wool/Glass/Stained Clay, End Portal Frame
		0x70 => [0x6C, 0x24, 0x24], // Red Wool/Glass/Stained Clay, Huge Red Mushroom, Brick, Enchanting Table
		0x71 => [0x84, 0x2C, 0x2C], // Red Wool/Glass/Stained Clay, Huge Red Mushroom, Brick, Enchanting Table
		0x72 => [0x99, 0x33, 0x33], // Red Wool/Glass/Stained Clay, Huge Red Mushroom, Brick, Enchanting Table
		0x73 => [0x51, 0x1B, 0x1B], // Red Wool/Glass/Stained Clay, Huge Red Mushroom, Brick, Enchanting Table
		0x74 => [0x11, 0x11, 0x11], // Black Wool/Glass/Stained Clay, Dragon Egg, Block of Coal, Obsidian
		0x75 => [0x15, 0x15, 0x15], // Black Wool/Glass/Stained Clay, Dragon Egg, Block of Coal, Obsidian
		0x76 => [0x19, 0x19, 0x19], // Black Wool/Glass/Stained Clay, Dragon Egg, Block of Coal, Obsidian
		0x77 => [0x0D, 0x0D, 0x0D], // Black Wool/Glass/Stained Clay, Dragon Egg, Block of Coal, Obsidian
		0x78 => [0xB0, 0xA8, 0x36], // Block of Gold, Weighted Pressure Plate (Light)
		0x79 => [0xD7, 0xCD, 0x42], // Block of Gold, Weighted Pressure Plate (Light)
		0x7A => [0xFA, 0xEE, 0x4D], // Block of Gold, Weighted Pressure Plate (Light)
		0x7B => [0x84, 0x7E, 0x28], // Block of Gold, Weighted Pressure Plate (Light)
		0x7C => [0x40, 0x9A, 0x96], // Block of Diamond, Prismarine, Prismarine Bricks, Dark Prismarine, Beacon
		0x7D => [0x4F, 0xBC, 0xB7], // Block of Diamond, Prismarine, Prismarine Bricks, Dark Prismarine, Beacon
		0x7E => [0x5C, 0xDB, 0xD5], // Block of Diamond, Prismarine, Prismarine Bricks, Dark Prismarine, Beacon
		0x7F => [0x30, 0x73, 0x70], // Block of Diamond, Prismarine, Prismarine Bricks, Dark Prismarine, Beacon
		0x80 => [0x34, 0x5A, 0xB4], // Lapis Lazuli Block
		0x81 => [0x3F, 0x6E, 0xDC], // Lapis Lazuli Block
		0x82 => [0x4A, 0x80, 0xFF], // Lapis Lazuli Block
		0x83 => [0x27, 0x43, 0x87], // Lapis Lazuli Block
		0x84 => [0x00, 0x99, 0x28], // Block of Emerald
		0x85 => [0x00, 0xBB, 0x32], // Block of Emerald
		0x86 => [0x00, 0xD9, 0x3A], // Block of Emerald
		0x87 => [0x00, 0x72, 0x1E], // Block of Emerald
		0x88 => [0x5A, 0x3B, 0x22], // Podzol, Spruce Plank
		0x89 => [0x6E, 0x49, 0x29], // Podzol, Spruce Plank
		0x8A => [0x7F, 0x55, 0x30], // Podzol, Spruce Plank
		0x8B => [0x43, 0x2C, 0x19], // Podzol, Spruce Plank
		0x8C => [0x4F, 0x01, 0x00], // Netherrack, Quartz Ore, Nether Wart, Nether Brick Items
		0x8D => [0x60, 0x01, 0x00], // Netherrack, Quartz Ore, Nether Wart, Nether Brick Items
		0x8E => [0x70, 0x02, 0x00], // Netherrack, Quartz Ore, Nether Wart, Nether Brick Items
		0x8F => [0x3B, 0x01, 0x00], // Netherrack, Quartz Ore, Nether Wart, Nether Brick Items
	];

	private static ?string $index = null;

	/**
	 * @var string $path
	 * @internal
	 */
	public static function generateColorIndex(string $path) : void{
		$indexes = "";

		for($r = 0; $r < 256; ++$r){
			for($g = 0; $g < 256; ++$g){
				for($b = 0; $b < 256; ++$b){
					$ind = 0x00;
					$min = PHP_INT_MAX;

					foreach(self::$colorTable as $index => $rgb){
						$squared = ($rgb[0] - $r) ** 2 + ($rgb[1] - $g) ** 2 + ($rgb[2] - $b) ** 2;
						if($squared < $min){
							$ind = $index;
							$min = $squared;
						}
					}

					$indexes .= chr($ind);
				}
			}
		}

		file_put_contents($path, zlib_encode($indexes, ZLIB_ENCODING_DEFLATE, 9));
	}

	public static function loadColorIndex(string $path) : void{
		self::$index = zlib_decode(file_get_contents($path));
	}

	/**
	 * Find nearest color defined in self::$colorTable for each pixel in $colors
	 *
	 * @param Color[][] $colors
	 * @param int       $width
	 * @param int       $height
	 *
	 * @return string
	 */
	public static function convertColorsToPC(array $colors, int $width, int $height) : string{
		$ret = "";

		for($y = 0; $y < $height; ++$y){
			for($x = 0; $x < $width; ++$x){
				$ret .= $colors[$y][$x]->getA() >= 128 ? self::$index[($colors[$y][$x]->getR() << 16) + ($colors[$y][$x]->getG() << 8) + $colors[$y][$x]->getB()] : chr(0x00);
			}
		}

		return $ret;
	}
}
