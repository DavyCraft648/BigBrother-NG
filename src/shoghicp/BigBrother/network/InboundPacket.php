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

namespace shoghicp\BigBrother\network;

use ErrorException;
use function get_class;

abstract class InboundPacket extends Packet{
	//Play
	const ACCEPT_TELEPORTATION_PACKET = 0x00;
	//TODO QUERY_BLOCK_NBT_PACKET = 0x01;
	//TODO SET_DIFFICULTY_PACKET = 0x02;
	const CHAT_PACKET = 0x03;
	const CLIENT_COMMAND_PACKET = 0x04;
	const CLIENT_INFORMATION_PACKET = 0x05;
	const COMMAND_SUGGESTION_PACKET = 0x06;
	const WINDOW_CONFIRMATION_PACKET = 0x07;
	//TODO CLICK_WINDOW_BUTTON_PACKET = 0x08;
	const CONTAINER_CLICK_PACKET = 0x09;
	const CONTAINER_CLOSE_PACKET = 0x0a;
	const CUSTOM_PAYLOAD_PACKET = 0x0b;
	//TODO EDIT_BOOK_PACKET = 0x0c;
	//TODO QUERY_ENTITY_NBT_PACKET = 0x0d;
	const INTERACT_PACKET = 0x0e;
	//TODO GENERATE_STRUCTURE_PACKET = 0x0f;
	const KEEP_ALIVE_PACKET = 0x10;
	//TODO LOCK_DIFFICULTY_PACKET = 0x11;
	const MOVE_PLAYER_POSITION_PACKET = 0x12;
	const MOVE_PLAYER_POSITION_AND_ROTATION_PACKET = 0x13;
	const MOVE_PLAYER_ROTATION_PACKET = 0x14;
	const MOVE_PLAYER_STATUS_ONLY_PACKET = 0x15;
	//TODO VEHICLE_MOVE_PACKET = 0x16;
	//TODO STEER_BOAT_PACKET = 0x17;
	//TODO PICK_ITEM_PACKET = 0x18;
	const PLACE_RECIPE_PACKET = 0x19;
	const PLAYER_ABILITIES_PACKET = 0x1a;
	const PLAYER_ACTION_PACKET = 0x1b;
	const PLAYER_COMMAND_PACKET = 0x1c;
	//TODO STEER_VEHICLE_PACKET = 0x1d;
	//TODO SET_DISPLAYED_RECIPE_PACKET = 0x1e;
	//TODO SET_RECIPE_BOOK_STATE_PACKET = 0x1f;
	//TODO NAME_ITEM_PACKET = 0x20;
	//TODO RESOURCE_PACK_STATUS_PACKET = 0x21;
	const SEEN_ADVANCEMENTS_PACKET = 0x22;
	//TODO SELECT_TRADE_PACKET = 0x23;
	//TODO SET_BEACON_EFFECT_PACKET = 0x24;
	const SET_CARRIED_ITEM_PACKET = 0x25;
	//TODO UPDATE COMMAND_BLOCK_PACKET = 0x26;
	//TODO UPDATE COMMAND_BLOCK_MINECRAFT_PACKET = 0x27;
	const SET_CREATIVE_MODE_SLOT_PACKET = 0x28;
	//TODO UPDATE_JIGSAW_BLOCK_PACKET = 0x29;
	//TODO UPDATE_STRUCTURE_BLOCK_PACKET = 0x2a;
	const SIGN_UPDATE_PACKET = 0x2b;
	const SWING_PACKET = 0x2c;
	//TODO SPECTATE_PACKET = 0x2d;
	const USE_ITEM_ON_PACKET = 0x2e;
	const USE_ITEM_PACKET = 0x2f;

	//Status

	//Login
	const HELLO_PACKET = 0x00;
	const KEY_PACKET = 0x01;

	/**
	 * @throws ErrorException
	 * @deprecated
	 */
	protected final function encode() : void{
		throw new ErrorException(get_class($this) . " is subclass of InboundPacket: don't call encode() method");
	}
}
